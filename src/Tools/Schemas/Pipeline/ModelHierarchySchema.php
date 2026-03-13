<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class ModelHierarchySchema
{
    /**
     * @param array<string> $classes
     */
    public function __construct(
        public string $rootClass,
        public array $classes,
    ) {
    }
}
