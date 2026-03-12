<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\DBIntrospection;

final readonly class TableIntrospectionDto
{
    /**
     * @param array<string, ColumnIntrospectionDto> $fields
     * @param list<string> $primaryKey
     * @param array<string, ForeignKeyIntrospectionDto> $foreignKeys
     * @param list<list<string>> $uniqueConstraints
     */
    public function __construct(
        public string $name,
        public array $fields,
        public array $primaryKey,
        public array $foreignKeys,
        public array $uniqueConstraints,
        public bool $isManyMany,
    ) {
    }
}

