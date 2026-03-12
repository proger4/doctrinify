<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Storage;

use Doctrinify\Tools\Tasks\Contracts\TaskResultDto;

final class TaskResultRepository
{
    public function __construct(private readonly string $projectRoot)
    {
    }

    /**
     * @return list<TaskResultDto>
     */
    public function findByTaskSet(string $storageRoot, string $taskSet): array
    {
        $base = $this->resolveBasePath($storageRoot, $taskSet) . '/results';
        if (!is_dir($base)) {
            return [];
        }

        $files = glob($base . '/result__*.json') ?: [];
        sort($files);

        $results = [];
        foreach ($files as $file) {
            $contents = file_get_contents($file);
            if (!is_string($contents) || $contents === '') {
                continue;
            }

            $decoded = json_decode($contents, true);
            if (!is_array($decoded)) {
                continue;
            }

            $suffix = basename($file, '.json');
            $suffix = str_replace('result__', '', $suffix);
            $mdPath = $base . '/result__' . $suffix . '.md';
            $markdown = is_file($mdPath) ? (string) file_get_contents($mdPath) : '';

            $results[] = new TaskResultDto(
                taskId: (string) ($decoded['task_id'] ?? $suffix),
                taskSet: (string) ($decoded['task_set'] ?? $taskSet),
                category: (string) ($decoded['category'] ?? 'general'),
                json: $decoded,
                markdown: $markdown,
                createdAt: (string) ($decoded['created_at'] ?? ''),
            );
        }

        return $results;
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
}
