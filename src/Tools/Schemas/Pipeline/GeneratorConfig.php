<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class GeneratorConfig
{
    /**
     * @param array<string> $baseClasses
     * @param array<string> $blacklist
     * @param array<string> $modelScanExcludeDirs
     */
    public function __construct(
        public string $modelsPath,
        public string $xmlOutputPath,
        public string $schemaPath,
        public string $classListPath,
        public bool $generateXml,
        public bool $generatePhp,
        public bool $tracePipeline,
        public array $baseClasses,
        public array $blacklist,
        public array $modelScanExcludeDirs,
    ) {
    }
}
