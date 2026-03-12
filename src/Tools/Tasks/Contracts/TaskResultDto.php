<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Contracts;

final class TaskResultDto
{
    /**
     * @param array<string, mixed> $json
     */
    public function __construct(
        public string $taskId,
        public string $taskSet,
        public string $category,
        public array $json,
        public string $markdown,
        public string $createdAt,
    ) {
    }
}
