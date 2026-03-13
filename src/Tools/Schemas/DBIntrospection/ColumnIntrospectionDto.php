<?php

declare(strict_types=1);

namespace App\Tools\Schemas\DBIntrospection;

final readonly class ColumnIntrospectionDto
{
    public function __construct(
        public string $name,
        public string $type,
        public bool $nullable,
    ) {
    }
}

