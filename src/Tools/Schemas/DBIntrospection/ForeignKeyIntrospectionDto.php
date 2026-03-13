<?php

declare(strict_types=1);

namespace App\Tools\Schemas\DBIntrospection;

final readonly class ForeignKeyIntrospectionDto
{
    /**
     * @param array<string> $columns
     * @param array<string> $referencedColumns
     */
    public function __construct(
        public string $name,
        public array $columns,
        public string $referencedTable,
        public array $referencedColumns,
        public bool $isOneToOne,
    ) {
    }
}

