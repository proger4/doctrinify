<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

final readonly class ModelRelationSchema
{
    /**
     * @param array<string, string> $mapping
     * @param list<string> $queryModifiers
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
