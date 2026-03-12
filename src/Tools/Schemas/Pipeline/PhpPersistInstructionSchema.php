<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

final readonly class PhpPersistInstructionSchema
{
    /**
     * @param list<object> $astNodes
     * @param list<string> $headerComments
     */
    public function __construct(
        public string $mode,
        public string $targetPath,
        public string $className,
        public ?string $content,
        public array $astNodes,
        public array $headerComments,
    ) {
    }
}
