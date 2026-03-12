<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

final readonly class CodebaseInput
{
    /**
     * @param list<string> $classes
     * @param array<string, string> $classFiles
     * @param list<string> $warnings
     */
    public function __construct(
        public GeneratorConfig $config,
        public array $classes,
        public array $classFiles = [],
        public array $warnings = [],
    ) {
    }
}
