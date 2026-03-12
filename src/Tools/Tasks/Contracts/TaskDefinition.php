<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Contracts;

final class TaskDefinition
{
    public function __construct(
        public string $taskId,
        public string $name,
        public string $category,
        public bool $done,
        public string $description,
        public string $artifactName,
        public string $resultArtifact,
    ) {
    }
}
