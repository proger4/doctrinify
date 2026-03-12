<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Execution;

use Doctrinify\Tools\Tasks\Contracts\TaskDefinition;
use Doctrinify\Tools\Tasks\Storage\TaskArtifactWriter;
use Doctrinify\Tools\Tasks\Storage\TaskSheetReader;

final class TaskRunService
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?TaskSheetReader $sheetReader = null,
        private readonly ?PromptRenderer $promptRenderer = null,
        private readonly ?AiTaskExecutor $taskExecutor = null,
        private readonly ?TaskArtifactWriter $artifactWriter = null,
        private readonly ?TaskInputBuilder $taskInputBuilder = null,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     * @return array{executed:int,skipped_done:int,task_set:string,storage_root:string}
     */
    public function run(
        string $xlsxFile,
        string $taskSet,
        ?string $taskId,
        ?string $category,
        array $options = [],
    ): array {
        $storageRoot = (string) ($options['task_storage_root'] ?? 'var/tasks');
        $maxInputLength = (int) ($options['max_input_length'] ?? 12000);
        $weakModelMode = (bool) ($options['weak_model_mode'] ?? true);

        $reader = $this->sheetReader ?? new TaskSheetReader();
        $renderer = $this->promptRenderer ?? new PromptRenderer();
        $executor = $this->taskExecutor ?? new AiTaskExecutor();
        $writer = $this->artifactWriter ?? new TaskArtifactWriter($this->projectRoot);
        $inputBuilder = $this->taskInputBuilder ?? new TaskInputBuilder($this->projectRoot);

        $workbook = $reader->read($this->resolvePath($xlsxFile));
        $tasks = $this->filterTasks($workbook->tasks, $taskSet, $taskId, $category);

        $executed = 0;
        $skippedDone = 0;

        foreach ($tasks as $task) {
            if ($task->done) {
                $skippedDone++;
                continue;
            }

            $artifactDef = $workbook->artifacts[$task->artifactName] ?? null;
            if (!is_array($artifactDef)) {
                continue;
            }

            $promptName = (string) ($artifactDef['prompt_name'] ?? '');
            $promptDef = $workbook->prompts[$promptName] ?? null;
            $basePrompt = $promptDef?->body ?? '';

            $inputMap = $inputBuilder->build(
                inputNames: is_array($artifactDef['inputs'] ?? null) ? $artifactDef['inputs'] : [],
                workbookInputs: $workbook->inputs,
                maxInputLength: $maxInputLength,
            );

            $effectiveTaskSet = $taskSet !== '' ? $taskSet : $task->category;
            $prompt = $renderer->render($task, $effectiveTaskSet, $basePrompt, $inputMap);
            $result = $executor->execute($task, $effectiveTaskSet, $inputMap, $weakModelMode);
            $writer->writeTaskRunArtifacts($storageRoot, $effectiveTaskSet, $task->taskId, $inputMap, $prompt, $result);

            $executed++;
        }

        return [
            'executed' => $executed,
            'skipped_done' => $skippedDone,
            'task_set' => $taskSet !== '' ? $taskSet : 'mixed',
            'storage_root' => $storageRoot,
        ];
    }

    /**
     * @param list<TaskDefinition> $tasks
     * @return list<TaskDefinition>
     */
    private function filterTasks(array $tasks, string $taskSet, ?string $taskId, ?string $category): array
    {
        $out = [];
        foreach ($tasks as $task) {
            if ($taskSet !== '' && $task->category !== strtolower($taskSet) && $task->category !== $taskSet) {
                continue;
            }

            if ($taskId !== null && $taskId !== '' && $task->taskId !== $taskId) {
                continue;
            }

            if ($category !== null && $category !== '' && $task->category !== strtolower($category)) {
                continue;
            }

            $out[] = $task;
        }

        return $out;
    }

    private function resolvePath(string $path): string
    {
        if ($path === '') {
            return $this->projectRoot;
        }

        if ($path[0] === '/') {
            return $path;
        }

        return rtrim($this->projectRoot, '/') . '/' . ltrim($path, './');
    }
}
