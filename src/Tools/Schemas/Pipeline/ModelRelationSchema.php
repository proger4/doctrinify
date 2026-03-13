<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class ModelRelationSchema
{
    /**
     * @param array<string, string> $mapping
     * @param array<string> $queryModifiers
     */
    public function __construct(
        public string $name,
        public string $kind,
        public string $target,
        public array $mapping,
        public array $queryModifiers,
    ) {
    }
}
