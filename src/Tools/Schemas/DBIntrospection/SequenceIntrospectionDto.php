<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\DBIntrospection;

final readonly class SequenceIntrospectionDto
{
    public function __construct(
        public string $name,
        public ?int $startWith = null,
        public ?int $incrementBy = null,
    ) {
    }
}

