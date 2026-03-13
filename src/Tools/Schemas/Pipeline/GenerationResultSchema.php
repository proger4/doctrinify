<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class GenerationResultSchema
{
    /**
     * @param array<GeneratedArtifactSchema> $artifacts
     */
    public function __construct(
        public array $artifacts,
        public string $report,
    ) {
    }
}
