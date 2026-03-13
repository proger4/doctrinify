<?php

declare(strict_types=1);

namespace App\Tools\Introspection;

interface PathIntrospectorInterface
{
    /**
     * @param array<string, mixed> $options
     */
    public function introspect(string $path, array $options = []);
}

