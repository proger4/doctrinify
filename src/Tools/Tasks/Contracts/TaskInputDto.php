<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Contracts;

final class TaskInputDto
{
    public function __construct(
        public string $name,
        public string $sourceRef,
        public string $policy,
        public string $content,
    ) {
    }
}
