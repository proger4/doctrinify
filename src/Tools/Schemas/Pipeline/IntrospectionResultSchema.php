<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas\Pipeline;

use Doctrinify\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;

final readonly class IntrospectionResultSchema
{
    /**
     * @param array<string, ModelIntrospectionSchema> $models
     */
    public function __construct(
        public array $models,
        public DatabaseIntrospectionDto $database,
    ) {
    }
}
