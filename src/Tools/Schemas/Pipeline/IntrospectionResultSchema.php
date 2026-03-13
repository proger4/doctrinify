<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

use Doctrinify\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;

final readonly class IntrospectionResultSchema
{
    /**
     * @param array<string, ModelIntrospectionSchema> $models
     * @param list<ModelHierarchySchema> $hierarchies
     */
    public function __construct(
        public array $models,
        public DatabaseIntrospectionDto $database,
        public array $hierarchies = [],
    ) {
    }
}
