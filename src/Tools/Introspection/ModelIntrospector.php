<?php

declare(strict_types=1);

namespace App\Tools\Introspection;

use App\Tools\AST\AstFacade;
use App\Tools\Schemas\Pipeline\ModelIntrospectionSchema;

final class ModelIntrospector
    implements PathIntrospectorInterface
{
    public function __construct(private readonly ?AstFacade $astFacade = null)
    {
    }

    /**
     * @return array<string>
     */
    private function relationSqlModifiers(): array
    {
        return [
            'where',
            'andWhere',
            'orWhere',
            'onCondition',
            'orderBy',
            'addOrderBy',
            'groupBy',
            'having',
            'joinWith',
            'innerJoin',
            'leftJoin',
            'rightJoin',
            'viaTable',
            'condition',
            'order',
            'joinType',
        ];
    }

    /**
     * @param array<string, mixed> $options
     */
    public function introspect(string $path, array $options = []): ModelIntrospectionSchema
    {
        $className = $options['class'] ?? null;
        if (!is_string($className) || $className === '') {
            throw new \InvalidArgumentException('ModelIntrospector requires options["class"]');
        }

        return $this->introspectModel($className, $path);
    }

    public function introspectModel(string $className, string $file): ModelIntrospectionSchema
    {
        $meta = ($this->astFacade ?? new AstFacade())->introspectModel($className, $file, $this->relationSqlModifiers());
        if ($meta !== null) {
            return $meta;
        }

        throw new \RuntimeException(sprintf('AST introspection failed for model `%s` at `%s`', $className, $file));
    }
}
