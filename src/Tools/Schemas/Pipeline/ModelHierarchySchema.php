<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

final readonly class ModelHierarchySchema
{
    /**
     * @param list<string> $classes
     */
    public function __construct(
        public string $rootClass,
        public array $classes,
    ) {
    }
}
