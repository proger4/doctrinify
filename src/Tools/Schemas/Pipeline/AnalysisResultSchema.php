<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class AnalysisResultSchema
{
    /**
     * @param array<string, AnalyzedModelSchema> $models
     * @param array<string, array{table:string, classes:list<string>}> $stiByRoot
     * @param array<Diagnostic> $diagnostics
     */
    public function __construct(
        public array $models,
        public array $stiByRoot,
        public array $diagnostics,
    ) {
    }
}
