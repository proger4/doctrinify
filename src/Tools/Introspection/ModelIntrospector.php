<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Introspection;

use Doctrinify\Tools\Schemas\Pipeline\ModelIntrospectionSchema;
use Doctrinify\Tools\Schemas\Pipeline\ModelRelationSchema;

final class ModelIntrospector
{
    /**
     * @return list<string>
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

    public function introspect(string $className, string $file, bool $useAst): ModelIntrospectionSchema
    {
        $regexMeta = $this->parseModelWithRegex($className, $file);
        if (!$useAst || !class_exists(\PhpParser\ParserFactory::class)) {
            return $regexMeta;
        }

        $astMeta = $this->parseModelWithAst($className, $file);
        if ($astMeta === null) {
            return $regexMeta;
        }

        return new ModelIntrospectionSchema(
            className: $className,
            extends: $astMeta->extends ?? $regexMeta->extends,
            table: $astMeta->table ?? $regexMeta->table,
            relations: $astMeta->relations !== [] ? $astMeta->relations : $regexMeta->relations,
            discriminator: $astMeta->discriminator ?? $regexMeta->discriminator,
            rules: $astMeta->rules !== [] ? $astMeta->rules : $regexMeta->rules,
            attributeLabels: $astMeta->attributeLabels !== [] ? $astMeta->attributeLabels : $regexMeta->attributeLabels,
            primaryKey: $astMeta->primaryKey !== [] ? $astMeta->primaryKey : $regexMeta->primaryKey,
        );
    }

    private function parseModelWithRegex(string $className, string $file): ModelIntrospectionSchema
    {
        $content = file_get_contents($file);
        if ($content === false) {
            throw new \RuntimeException(sprintf('Failed to read model file: %s', $file));
        }

        $extends = null;
        if (preg_match('/class\s+' . preg_quote($this->shortClassName($className), '/') . '\s+extends\s+([A-Za-z0-9_\\\\]+)/', $content, $extMatch) === 1) {
            $extends = $extMatch[1];
            if (!str_contains($extends, '\\')) {
                $namespace = substr($className, 0, (int) strrpos($className, '\\'));
                $extends = $namespace . '\\' . $extends;
            }
        }

        $table = null;
        if (preg_match('/function\s+tableName\s*\(\)\s*\{[^\}]*return\s+[\'\"]([^\'\"]+)[\'\"]/s', $content, $tableMatch) === 1) {
            $table = $tableMatch[1];
        }

        $relations = [];
        preg_match_all('/function\s+get([A-Za-z0-9_]+)\s*\(\)\s*\{(.*?)\}/s', $content, $methodMatches, PREG_SET_ORDER);
        foreach ($methodMatches as $methodMatch) {
            $field = lcfirst($methodMatch[1]);
            $body = $methodMatch[2];

            if (preg_match('/return\s+\$this->(hasOne|hasMany)\(\s*([^,]+)::class\s*,\s*\[([^\]]*)\]/', $body, $relMatch) !== 1) {
                continue;
            }

            $kind = $relMatch[1] === 'hasOne' ? 'many-to-one' : 'one-to-many';
            $targetRaw = trim($relMatch[2]);
            $target = $targetRaw === 'self' ? $className : $this->qualifyTarget($className, $targetRaw);

            $mapping = [];
            preg_match_all('/[\'\"]([a-zA-Z0-9_]+)[\'\"]\s*=>\s*[\'\"]([a-zA-Z0-9_]+)[\'\"]/', $relMatch[3], $mapMatches, PREG_SET_ORDER);
            foreach ($mapMatches as $mapMatch) {
                $mapping[$mapMatch[1]] = $mapMatch[2];
            }

            $relations[] = new ModelRelationSchema(
                name: $field,
                kind: $kind,
                target: $target,
                mapping: $mapping,
                queryModifiers: $this->extractQueryModifiersFromBody($body),
            );
        }

        $discriminator = null;
        if (preg_match('/where\s*\(\s*\[\s*[\'\"]type[\'\"]\s*=>\s*[\'\"]([^\'\"]+)[\'\"]/', $content, $typeMatch) === 1) {
            $discriminator = $typeMatch[1];
        } elseif (preg_match('/\$this->type\s*=\s*[\'\"]([^\'\"]+)[\'\"]/', $content, $typeSaveMatch) === 1) {
            $discriminator = $typeSaveMatch[1];
        }

        $rules = [];
        if (preg_match('/function\s+rules\s*\(\)\s*\{(.*?)\}/s', $content, $rulesMethod) === 1
            && preg_match('/return\s*\[(.*?)\];/s', $rulesMethod[1], $rulesReturn) === 1) {
            preg_match_all('/\[(.*?)\]/s', $rulesReturn[1], $ruleMatches);
            $rules = array_values(array_filter(array_map(static fn (string $rule): string => trim(preg_replace('/\s+/', ' ', $rule) ?? ''), $ruleMatches[1])));
        }

        $labels = [];
        if (preg_match('/function\s+attributeLabels\s*\(\)\s*\{(.*?)\}/s', $content, $labelsMethod) === 1
            && preg_match('/return\s*\[(.*?)\];/s', $labelsMethod[1], $labelsReturn) === 1) {
            preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]\s*=>\s*[\'"]([^\'"]+)[\'"]/', $labelsReturn[1], $labelMatches, PREG_SET_ORDER);
            foreach ($labelMatches as $labelMatch) {
                $labels[$labelMatch[1]] = $labelMatch[2];
            }
        }

        $primaryKey = [];
        if (preg_match('/function\s+primaryKey\s*\(\)\s*\{(.*?)\}/s', $content, $pkMethod) === 1
            && preg_match('/return\s*\[(.*?)\];/s', $pkMethod[1], $pkReturn) === 1) {
            preg_match_all('/[\'"]([a-zA-Z0-9_]+)[\'"]/', $pkReturn[1], $pkMatches);
            $primaryKey = $pkMatches[1];
        }

        return new ModelIntrospectionSchema(
            className: $className,
            extends: $extends,
            table: $table,
            relations: $relations,
            discriminator: $discriminator,
            rules: $rules,
            attributeLabels: $labels,
            primaryKey: $primaryKey,
        );
    }

    private function parseModelWithAst(string $className, string $file): ?ModelIntrospectionSchema
    {
        $code = file_get_contents($file);
        if ($code === false) {
            return null;
        }

        try {
            $factory = new \PhpParser\ParserFactory();
            $parser = method_exists($factory, 'createForNewestSupportedVersion')
                ? $factory->createForNewestSupportedVersion()
                : $factory->createForHostVersion();
            $ast = $parser->parse($code);
        } catch (\Throwable) {
            return null;
        }

        if (!is_array($ast)) {
            return null;
        }

        $targetShortName = $this->shortClassName($className);
        $classNode = null;
        foreach ($ast as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\Namespace_) {
                foreach ($stmt->stmts as $nsStmt) {
                    if ($nsStmt instanceof \PhpParser\Node\Stmt\Class_ && $nsStmt->name?->toString() === $targetShortName) {
                        $classNode = $nsStmt;
                        break 2;
                    }
                }
            }
            if ($stmt instanceof \PhpParser\Node\Stmt\Class_ && $stmt->name?->toString() === $targetShortName) {
                $classNode = $stmt;
                break;
            }
        }

        if (!$classNode instanceof \PhpParser\Node\Stmt\Class_) {
            return null;
        }

        $extends = null;
        if ($classNode->extends instanceof \PhpParser\Node\Name) {
            $extends = $classNode->extends->toString();
            if (!str_contains($extends, '\\')) {
                $namespace = substr($className, 0, (int) strrpos($className, '\\'));
                $extends = $namespace . '\\' . $extends;
            }
        }

        $table = null;
        $relations = [];
        $discriminator = null;
        $rules = [];
        $labels = [];
        $primaryKey = [];

        foreach ($classNode->getMethods() as $method) {
            $name = $method->name->toString();
            if ($name === 'tableName') {
                $table = $this->extractReturnedString($method) ?? $table;
                continue;
            }
            if ($name === 'primaryKey') {
                $primaryKey = $this->extractReturnedStringArray($method);
                continue;
            }
            if ($name === 'rules') {
                $rules = $this->extractRulesFromMethod($method);
                continue;
            }
            if ($name === 'attributeLabels') {
                $labels = $this->extractAssocStringMapFromMethod($method);
                continue;
            }
            if ($name === 'find') {
                $discriminator = $this->extractTypeDiscriminatorFromFind($method) ?? $discriminator;
                continue;
            }
            if ($name === 'beforeSave') {
                $discriminator = $this->extractTypeDiscriminatorFromBeforeSave($method) ?? $discriminator;
                continue;
            }
            if (!str_starts_with($name, 'get')) {
                continue;
            }

            $relation = $this->extractRelationFromMethod($className, $method);
            if ($relation !== null) {
                $relations[] = $relation;
            }
        }

        return new ModelIntrospectionSchema(
            className: $className,
            extends: $extends,
            table: $table,
            relations: $relations,
            discriminator: $discriminator,
            rules: $rules,
            attributeLabels: $labels,
            primaryKey: $primaryKey,
        );
    }

    private function extractReturnedString(\PhpParser\Node\Stmt\ClassMethod $method): ?string
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\Return_
                && $stmt->expr instanceof \PhpParser\Node\Scalar\String_) {
                return $stmt->expr->value;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function extractReturnedStringArray(\PhpParser\Node\Stmt\ClassMethod $method): array
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if (!$stmt instanceof \PhpParser\Node\Stmt\Return_ || !$stmt->expr instanceof \PhpParser\Node\Expr\Array_) {
                continue;
            }

            $items = [];
            foreach ($stmt->expr->items as $item) {
                if ($item !== null && $item->value instanceof \PhpParser\Node\Scalar\String_) {
                    $items[] = $item->value->value;
                }
            }
            return $items;
        }

        return [];
    }

    /**
     * @return list<string>
     */
    private function extractRulesFromMethod(\PhpParser\Node\Stmt\ClassMethod $method): array
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if (!$stmt instanceof \PhpParser\Node\Stmt\Return_ || !$stmt->expr instanceof \PhpParser\Node\Expr\Array_) {
                continue;
            }

            $rules = [];
            foreach ($stmt->expr->items as $item) {
                if ($item !== null && $item->value instanceof \PhpParser\Node\Expr\Array_) {
                    $rules[] = $this->normalizeExpressionForReport($item->value);
                }
            }

            return $rules;
        }

        return [];
    }

    /**
     * @return array<string, string>
     */
    private function extractAssocStringMapFromMethod(\PhpParser\Node\Stmt\ClassMethod $method): array
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if (!$stmt instanceof \PhpParser\Node\Stmt\Return_ || !$stmt->expr instanceof \PhpParser\Node\Expr\Array_) {
                continue;
            }

            $labels = [];
            foreach ($stmt->expr->items as $item) {
                if ($item !== null
                    && $item->key instanceof \PhpParser\Node\Scalar\String_
                    && $item->value instanceof \PhpParser\Node\Scalar\String_) {
                    $labels[$item->key->value] = $item->value->value;
                }
            }

            return $labels;
        }

        return [];
    }

    private function extractTypeDiscriminatorFromFind(\PhpParser\Node\Stmt\ClassMethod $method): ?string
    {
        foreach ($method->stmts ?? [] as $stmt) {
            if (!$stmt instanceof \PhpParser\Node\Stmt\Return_ || !$stmt->expr instanceof \PhpParser\Node\Expr) {
                continue;
            }

            $whereArgs = $this->findMethodCallArgs($stmt->expr, 'where');
            if ($whereArgs === null || $whereArgs === []) {
                continue;
            }

            $firstArg = $whereArgs[0] ?? null;
            if (!$firstArg instanceof \PhpParser\Node\Arg || !$firstArg->value instanceof \PhpParser\Node\Expr\Array_) {
                continue;
            }

            foreach ($firstArg->value->items as $item) {
                if ($item !== null
                    && $item->key instanceof \PhpParser\Node\Scalar\String_
                    && $item->value instanceof \PhpParser\Node\Scalar\String_
                    && $item->key->value === 'type') {
                    return $item->value->value;
                }
            }
        }

        return null;
    }

    private function extractTypeDiscriminatorFromBeforeSave(\PhpParser\Node\Stmt\ClassMethod $method): ?string
    {
        $stmts = $method->stmts ?? [];
        foreach ($stmts as $stmt) {
            foreach ($this->findAssignments($stmt) as $assign) {
                if (!$assign->var instanceof \PhpParser\Node\Expr\PropertyFetch) {
                    continue;
                }
                if (!$assign->var->name instanceof \PhpParser\Node\Identifier) {
                    continue;
                }
                if ($assign->var->name->toString() !== 'type') {
                    continue;
                }
                if ($assign->expr instanceof \PhpParser\Node\Scalar\String_) {
                    return $assign->expr->value;
                }
            }
        }

        return null;
    }

    /**
     * @return list<\PhpParser\Node\Expr\Assign>
     */
    private function findAssignments(\PhpParser\Node $node): array
    {
        $found = [];
        if ($node instanceof \PhpParser\Node\Expr\Assign) {
            $found[] = $node;
        }

        foreach ($node->getSubNodeNames() as $name) {
            $sub = $node->$name;
            if ($sub instanceof \PhpParser\Node) {
                $found = array_merge($found, $this->findAssignments($sub));
                continue;
            }
            if (!is_array($sub)) {
                continue;
            }
            foreach ($sub as $item) {
                if ($item instanceof \PhpParser\Node) {
                    $found = array_merge($found, $this->findAssignments($item));
                }
            }
        }

        return $found;
    }

    private function extractRelationFromMethod(string $className, \PhpParser\Node\Stmt\ClassMethod $method): ?ModelRelationSchema
    {
        $returnExpr = null;
        foreach ($method->stmts ?? [] as $stmt) {
            if ($stmt instanceof \PhpParser\Node\Stmt\Return_ && $stmt->expr instanceof \PhpParser\Node\Expr) {
                $returnExpr = $stmt->expr;
                break;
            }
        }

        if (!$returnExpr instanceof \PhpParser\Node\Expr) {
            return null;
        }

        $call = $this->findMethodCallNode($returnExpr, ['hasOne', 'hasMany']);
        if (!$call instanceof \PhpParser\Node\Expr\MethodCall || !$call->name instanceof \PhpParser\Node\Identifier) {
            return null;
        }

        if (count($call->args) < 2) {
            return null;
        }

        $firstArg = $call->args[0] ?? null;
        $secondArg = $call->args[1] ?? null;
        if (!$firstArg instanceof \PhpParser\Node\Arg || !$secondArg instanceof \PhpParser\Node\Arg) {
            return null;
        }

        $kind = $call->name->toString() === 'hasOne' ? 'many-to-one' : 'one-to-many';
        $target = $this->extractTargetClass($className, $firstArg->value);
        $mapping = $this->extractRelationMapping($secondArg->value);
        $field = lcfirst(substr($method->name->toString(), 3));

        return new ModelRelationSchema(
            name: $field,
            kind: $kind,
            target: $target,
            mapping: $mapping,
            queryModifiers: $this->extractQueryModifiersFromExpression($returnExpr),
        );
    }

    private function extractTargetClass(string $className, \PhpParser\Node\Expr $expr): string
    {
        if ($expr instanceof \PhpParser\Node\Expr\ClassConstFetch
            && $expr->name instanceof \PhpParser\Node\Identifier
            && $expr->name->toString() === 'class') {
            $targetRaw = $expr->class->toString();
            if (strtolower($targetRaw) === 'self') {
                return $className;
            }
            return $this->qualifyTarget($className, $targetRaw);
        }

        return $className;
    }

    /**
     * @return array<string, string>
     */
    private function extractRelationMapping(\PhpParser\Node\Expr $expr): array
    {
        if (!$expr instanceof \PhpParser\Node\Expr\Array_) {
            return [];
        }

        $mapping = [];
        foreach ($expr->items as $item) {
            if ($item !== null
                && $item->key instanceof \PhpParser\Node\Scalar\String_
                && $item->value instanceof \PhpParser\Node\Scalar\String_) {
                $mapping[$item->key->value] = $item->value->value;
            }
        }

        return $mapping;
    }

    /**
     * @param array<int, string> $methodNames
     */
    private function findMethodCallNode(\PhpParser\Node\Expr $expr, array $methodNames): ?\PhpParser\Node\Expr\MethodCall
    {
        if ($expr instanceof \PhpParser\Node\Expr\MethodCall
            && $expr->name instanceof \PhpParser\Node\Identifier
            && in_array($expr->name->toString(), $methodNames, true)) {
            return $expr;
        }

        if ($expr instanceof \PhpParser\Node\Expr\MethodCall && $expr->var instanceof \PhpParser\Node\Expr) {
            return $this->findMethodCallNode($expr->var, $methodNames);
        }

        return null;
    }

    /**
     * @return array<int, \PhpParser\Node\Arg>|null
     */
    private function findMethodCallArgs(\PhpParser\Node\Expr $expr, string $methodName): ?array
    {
        if ($expr instanceof \PhpParser\Node\Expr\MethodCall
            && $expr->name instanceof \PhpParser\Node\Identifier
            && $expr->name->toString() === $methodName) {
            $args = [];
            foreach ($expr->args as $arg) {
                if ($arg instanceof \PhpParser\Node\Arg) {
                    $args[] = $arg;
                }
            }

            return $args;
        }

        if ($expr instanceof \PhpParser\Node\Expr\MethodCall && $expr->var instanceof \PhpParser\Node\Expr) {
            return $this->findMethodCallArgs($expr->var, $methodName);
        }

        return null;
    }

    private function normalizeExpressionForReport(\PhpParser\Node\Expr $expr): string
    {
        if ($expr instanceof \PhpParser\Node\Expr\Array_) {
            $parts = [];
            foreach ($expr->items as $item) {
                if ($item === null) {
                    continue;
                }
                $value = $item->value;
                if ($value instanceof \PhpParser\Node\Scalar\String_) {
                    $parts[] = "'" . $value->value . "'";
                    continue;
                }
                if ($value instanceof \PhpParser\Node\Expr\Array_) {
                    $parts[] = '[...]';
                    continue;
                }
                if ($value instanceof \PhpParser\Node\Scalar\LNumber) {
                    $parts[] = (string) $value->value;
                }
            }
            return '[' . implode(', ', $parts) . ']';
        }

        return '[]';
    }

    private function qualifyTarget(string $className, string $target): string
    {
        if (str_contains($target, '\\')) {
            return ltrim($target, '\\');
        }

        $namespace = substr($className, 0, (int) strrpos($className, '\\'));
        return $namespace . '\\' . $target;
    }

    private function shortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts) ?: $fqn;
    }

    /**
     * @return list<string>
     */
    private function extractQueryModifiersFromBody(string $body): array
    {
        $found = [];
        preg_match_all('/->\s*([A-Za-z_][A-Za-z0-9_]*)\s*\(/', $body, $matches);
        foreach ($matches[1] ?? [] as $methodName) {
            if (in_array($methodName, $this->relationSqlModifiers(), true) && !in_array($methodName, $found, true)) {
                $found[] = $methodName;
            }
        }

        foreach (['condition', 'order', 'joinType'] as $keyword) {
            if (preg_match('/[\'\"]' . preg_quote($keyword, '/') . '[\'\"]\s*=>/i', $body) === 1 && !in_array($keyword, $found, true)) {
                $found[] = $keyword;
            }
        }

        return $found;
    }

    /**
     * @return list<string>
     */
    private function extractQueryModifiersFromExpression(\PhpParser\Node\Expr $expr): array
    {
        $found = [];
        $current = $expr;
        while ($current instanceof \PhpParser\Node\Expr\MethodCall) {
            if ($current->name instanceof \PhpParser\Node\Identifier) {
                $methodName = $current->name->toString();
                if (in_array($methodName, $this->relationSqlModifiers(), true) && !in_array($methodName, $found, true)) {
                    $found[] = $methodName;
                }
            }

            foreach ($current->args as $arg) {
                if (!$arg instanceof \PhpParser\Node\Arg || !$arg->value instanceof \PhpParser\Node\Expr\Array_) {
                    continue;
                }

                foreach ($arg->value->items as $item) {
                    if ($item === null || !$item->key instanceof \PhpParser\Node\Scalar\String_) {
                        continue;
                    }
                    $key = $item->key->value;
                    if (in_array($key, ['condition', 'order', 'joinType'], true) && !in_array($key, $found, true)) {
                        $found[] = $key;
                    }
                }
            }

            if (!$current->var instanceof \PhpParser\Node\Expr) {
                break;
            }

            $current = $current->var;
        }

        return $found;
    }
}
