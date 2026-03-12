<?php

declare(strict_types=1);

namespace Doctrinify\Tools\AST;

final readonly class AstClassMutationPlan
{
    /**
     * @param list<object> $nodes
     * @param list<string> $headerComments
     */
    public function __construct(
        public string $targetPath,
        public string $className,
        public array $nodes,
        public array $headerComments,
    ) {
    }
}
