<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Storage;

use Doctrinify\Tools\Tasks\Contracts\TaskResultDto;

final class TaskArtifactWriter
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @param array<string, string> $inputs
     */
    public function writeTaskRunArtifacts(
        string $storageRoot,
        string $taskSet,
        string $taskId,
        array $inputs,
        string $prompt,
        TaskResultDto $result,
    ): void {
        $base = $this->resolveBasePath($storageRoot, $taskSet);
        $this->ensureDirectories($base);

        $suffix = $this->safeName($taskId);
        foreach ($inputs as $name => $content) {
            $path = $base . '/inputs/' . sprintf('%s__%s.txt', $this->safeName($name), $suffix);
            file_put_contents($path, $content);
        }

        file_put_contents($base . '/prompts/' . sprintf('prompt__%s.md', $suffix), $prompt);
        file_put_contents(
            $base . '/results/' . sprintf('result__%s.json', $suffix),
            (string) json_encode($result->json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
        file_put_contents($base . '/results/' . sprintf('result__%s.md', $suffix), $result->markdown);

        $record = [
            'task_id' => $result->taskId,
            'task_set' => $result->taskSet,
            'category' => $result->category,
            'timestamp' => $result->createdAt,
            'result_json' => 'results/' . sprintf('result__%s.json', $suffix),
            'result_md' => 'results/' . sprintf('result__%s.md', $suffix),
        ];
        file_put_contents(
            $base . '/done_artifacts.ndjson',
            (string) json_encode($record, JSON_UNESCAPED_SLASHES) . PHP_EOL,
            FILE_APPEND
        );
    }

    public function reportPath(string $storageRoot, string $taskSet): string
    {
        return $this->resolveBasePath($storageRoot, $taskSet) . '/reports/' . $taskSet . '.html';
    }

    private function resolveBasePath(string $storageRoot, string $taskSet): string
    {
        if ($storageRoot === '') {
            $storageRoot = 'var/tasks';
        }

        if ($storageRoot[0] === '/') {
            return rtrim($storageRoot, '/') . '/' . $taskSet;
        }

        return rtrim($this->projectRoot, '/') . '/' . trim($storageRoot, '/') . '/' . $taskSet;
    }

    private function ensureDirectories(string $base): void
    {
        foreach (['', '/inputs', '/prompts', '/results', '/reports'] as $suffix) {
            $path = $base . $suffix;
            if (!is_dir($path) && !mkdir($path, 0777, true) && !is_dir($path)) {
                throw new \RuntimeException(sprintf('Cannot create directory: %s', $path));
            }
        }
    }

    private function safeName(string $value): string
    {
        $safe = preg_replace('/[^a-zA-Z0-9_\-]+/', '_', $value) ?: 'task';
        return trim($safe, '_');
    }
}
