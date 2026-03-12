<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

final readonly class GenerationResultSchema
{
    /**
     * @param list<GeneratedArtifactSchema> $artifacts
     */
    public function __construct(
        public array $artifacts,
        public string $report,
    ) {
    }
}
