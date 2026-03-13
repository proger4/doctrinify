<?php

declare(strict_types=1);

namespace App\Tools\Config;

final readonly class PathResolver
{
    /**
     * @param array<string, string> $aliases
     */
    public function __construct(
        private string $projectRoot,
        private array $aliases = [],
    ) {
    }

    public function resolve(string $path): string
    {
        if ($path === '') {
            return $this->projectRoot;
        }

        $expanded = $this->expandAlias($path);
        if ($expanded === '') {
            return $this->projectRoot;
        }

        if ($expanded[0] === '/') {
            return $expanded;
        }

        return rtrim($this->projectRoot, '/') . '/' . ltrim($expanded, './');
    }

    private function expandAlias(string $path): string
    {
        if ($path[0] !== '@') {
            return $path;
        }

        if (!preg_match('/^@([a-zA-Z0-9_.-]+)(?:\/(.*))?$/', $path, $m)) {
            return $path;
        }

        $alias = $m[1];
        $tail = $m[2] ?? '';
        if (!isset($this->aliases[$alias])) {
            return $path;
        }

        $base = $this->aliases[$alias];
        if ($base === '') {
            return $tail;
        }

        if ($tail === '') {
            return $base;
        }

        return rtrim($base, '/') . '/' . ltrim($tail, '/');
    }
}

