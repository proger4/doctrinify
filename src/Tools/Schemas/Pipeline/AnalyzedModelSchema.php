<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class AnalyzedModelSchema
{
    /**
     * @param array<RelationDecisionSchema> $relations
     */
    public function __construct(
        public ModelIntrospectionSchema $model,
        public ?string $resolvedTable,
        public array $relations,
    ) {
    }
}
