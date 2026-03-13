<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class ModelIntrospectionSchema
{
    /**
     * @param array<ModelRelationSchema> $relations
     * @param array<string> $rules
     * @param array<string, string> $attributeLabels
     * @param array<string> $primaryKey
     */
    public function __construct(
        public string $className,
        public ?string $extends,
        public bool $isAbstract,
        public ?string $table,
        public array $relations,
        public ?string $discriminator,
        public array $rules,
        public array $attributeLabels,
        public array $primaryKey,
    ) {
    }
}
