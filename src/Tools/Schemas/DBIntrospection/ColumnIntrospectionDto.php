<?php

declare(strict_types=1);

namespace App\Tools\Schemas\DBIntrospection;

final class ColumnIntrospectionDto
{
    public string $name;
    public string $type;
    public bool $nullable;

    public function __construct(
        string $name,
        string $type,
        bool $nullable
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->nullable = $nullable;
    }
}
