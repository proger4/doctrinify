<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\DBIntrospection;

final readonly class ColumnIntrospectionDto
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
    ) {
    }
}

