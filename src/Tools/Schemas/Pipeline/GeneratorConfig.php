<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class GeneratorConfig
{
    /**
     * @param list<string> $baseClasses
     * @param list<string> $blacklist
     * @param list<string> $modelScanExcludeDirs
     */
    public function __construct(
        public string $modelsPath,
        public string $xmlOutputPath,
        public string $schemaPath,
        public string $classListPath,
        public bool $generateXml,
        public bool $generatePhp,
        public array $baseClasses,
        public array $blacklist,
        public array $modelScanExcludeDirs,
    ) {
    }
}
