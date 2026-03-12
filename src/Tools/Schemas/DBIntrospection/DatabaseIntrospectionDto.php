<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\DBIntrospection;

final readonly class DatabaseIntrospectionDto
{
    /**
     * @param array<string, TableIntrospectionDto> $tables
     * @param array<string, SequenceIntrospectionDto> $sequences
     */
    public function __construct(
        public array $tables,
        public array $sequences,
    ) {
    }
}

