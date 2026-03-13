<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class GeneratedArtifactSchema
{
    public function __construct(
        public string $className,
        public ?string $xmlFilename,
        public ?string $xml,
        public ?PhpPersistInstructionSchema $phpInstruction,
    ) {
    }
}
