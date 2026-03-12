<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Execution;

use Doctrinify\Tools\Tasks\Catalog\AlgorithmDiffTaskFactory;
use Doctrinify\Tools\Tasks\Catalog\ModelsAiAnalysisTaskFactory;
use Doctrinify\Tools\Tasks\Contracts\TaskDefinition;

final class PromptRenderer
{
    private ModelsAiAnalysisTaskFactory $modelsFactory;
    private AlgorithmDiffTaskFactory $diffFactory;

    public function __construct(
        ?ModelsAiAnalysisTaskFactory $modelsFactory = null,
        ?AlgorithmDiffTaskFactory $diffFactory = null,
    ) {
        $this->modelsFactory = $modelsFactory ?? new ModelsAiAnalysisTaskFactory();
        $this->diffFactory = $diffFactory ?? new AlgorithmDiffTaskFactory();
    }

    /**
     * @param array<string, string> $inputs
     */
    public function render(TaskDefinition $task, string $taskSet, string $basePrompt, array $inputs): string
    {
        $schema = $this->schemaFor($taskSet);
        $checklist = $this->checklistFor($taskSet);

        $lines = [];
        $lines[] = '# Task';
        $lines[] = sprintf('- id: %s', $task->taskId);
        $lines[] = sprintf('- name: %s', $task->name);
        $lines[] = sprintf('- category: %s', $task->category);
        if ($task->description !== '') {
            $lines[] = sprintf('- description: %s', $task->description);
        }
        $lines[] = '';
        $lines[] = '# Instructions';
        $lines[] = $basePrompt !== '' ? $basePrompt : 'Analyze inputs and return strict JSON output schema.';
        $lines[] = '';
        $lines[] = '# Output schema (strict JSON)';
        $lines[] = '```json';
        $lines[] = $schema;
        $lines[] = '```';
        $lines[] = '';
        $lines[] = '# Checklist';
        foreach ($checklist as $item) {
            $lines[] = '- ' . $item;
        }

        foreach ($inputs as $name => $content) {
            $lines[] = '';
            $lines[] = '## INPUT: ' . $name;
            $lines[] = '```text';
            $lines[] = $content;
            $lines[] = '```';
        }

        return implode(PHP_EOL, $lines) . PHP_EOL;
    }

    private function schemaFor(string $taskSet): string
    {
        if ($taskSet === 'algorithm_diff') {
            return $this->diffFactory->outputSchemaJson();
        }

        return $this->modelsFactory->outputSchemaJson();
    }

    /**
     * @return list<string>
     */
    private function checklistFor(string $taskSet): array
    {
        if ($taskSet === 'algorithm_diff') {
            return $this->diffFactory->checklist();
        }

        return $this->modelsFactory->checklist();
    }
}
