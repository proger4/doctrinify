<?php

declare(strict_types=1);

namespace App\Tools\Naming;

use function Symfony\Component\String\u;

final class ColumnNameMapper
{
    public function toFieldName(string $columnName): string
    {
        $normalized = trim($columnName);
        if ($normalized === '') {
            return '';
        }

        $snake = preg_replace('/[^a-zA-Z0-9]+/', '_', $normalized);
        $snake = is_string($snake) ? trim($snake, '_') : $normalized;

        return (string) u(strtolower($snake))->camel();
    }
}

