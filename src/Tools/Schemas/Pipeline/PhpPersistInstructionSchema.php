<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class PhpPersistInstructionSchema
{
    /**
     * @param array<object> $astNodes
     * @param array<string> $headerComments
     */
    public function __construct(
        public string $targetPath,
        public string $className,
        public array $astNodes,
        public array $headerComments,
    ) {
    }
}
