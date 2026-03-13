<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

use App\Tools\Schemas\DBIntrospection\SequenceIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\TableIntrospectionDto;

final readonly class AnalysisBatchSchema
{
    /**
     * @param array<int, string> $hierarchyClasses
     * @param array<string, SequenceIntrospectionDto> $sequences
     */
    public function __construct(
        public string $className,
        public AnalyzedModelSchema $analyzedModel,
        public ?TableIntrospectionDto $table,
        public array $hierarchyClasses,
        public array $sequences,
    ) {
    }
}

