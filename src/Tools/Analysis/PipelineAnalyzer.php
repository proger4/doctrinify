<?php

declare(strict_types=1);

namespace App\Tools\Analysis;

use App\Tools\Schemas\Pipeline\AnalyzedModelSchema;
use App\Tools\Schemas\Pipeline\AnalysisResultSchema;
use App\Tools\Schemas\Pipeline\Diagnostic;
use App\Tools\Schemas\Pipeline\IntrospectionResultSchema;
use App\Tools\Schemas\Pipeline\ModelHierarchySchema;
use App\Tools\Schemas\Pipeline\ModelIntrospectionSchema;
use App\Tools\Schemas\Pipeline\ModelRelationSchema;
use App\Tools\Schemas\Pipeline\RelationDecisionSchema;
use App\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\TableIntrospectionDto;

final class PipelineAnalyzer
{
    public function analyze(IntrospectionResultSchema $input): AnalysisResultSchema
    {
        $models = $input->models;
        $stiByRoot = $this->buildSingleTableInheritanceMap($models, $input->hierarchies);

        $analyzedModels = [];
        foreach ($models as $className => $model) {
            $resolvedTable = $this->resolveTable($model, $models);
            $decisions = [];
            foreach ($model->relations as $relation) {
                $rejectionReason = $this->relationRejectionReason($relation);
                $decisions[] = new RelationDecisionSchema(
                    relation: $relation,
                    accepted: $rejectionReason === null,
                    rejectionReason: $rejectionReason,
                );
            }

            $analyzedModels[$className] = new AnalyzedModelSchema(
                model: $model,
                resolvedTable: $resolvedTable,
                relations: $decisions,
            );
        }

        $diagnostics = $this->collectDiagnostics($analyzedModels, $input->database);

        return new AnalysisResultSchema(
            models: $analyzedModels,
            stiByRoot: $stiByRoot,
            diagnostics: $diagnostics,
        );
    }

    private function relationRejectionReason(ModelRelationSchema $relation): ?string
    {
        if ($relation->queryModifiers === []) {
            return null;
        }

        return sprintf('contains SQL query modifiers: %s', implode(', ', $relation->queryModifiers));
    }

    /**
     * @param array<string, AnalyzedModelSchema> $analyzedModels
     * @return list<Diagnostic>
     */
    private function collectDiagnostics(array $analyzedModels, DatabaseIntrospectionDto $schema): array
    {
        $diagnostics = [];
        $schemaIndex = $this->indexTablesCaseInsensitive($schema);
        $referencedTables = [];

        foreach ($analyzedModels as $className => $analyzedModel) {
            foreach ($analyzedModel->relations as $decision) {
                if ($decision->accepted) {
                    continue;
                }
                $diagnostics[] = new Diagnostic(
                    severity: 'warning',
                    message: sprintf('relation `%s` rejected: %s', $decision->relation->name, $decision->rejectionReason ?? 'rejected'),
                    context: [
                        'code' => 'RELATION_REJECTED',
                        'class' => $className,
                        'relation' => $decision->relation->name,
                    ],
                );
            }

            if ($analyzedModel->resolvedTable === null) {
                $diagnostics[] = new Diagnostic(
                    severity: 'error',
                    message: 'tableName unresolved',
                    context: ['code' => 'TABLE', 'class' => $className],
                );
                continue;
            }

            $table = $this->findTableByName($schemaIndex, $analyzedModel->resolvedTable);
            if ($table === null) {
                $diagnostics[] = new Diagnostic(
                    severity: 'error',
                    message: sprintf('table `%s` absent in schema', $analyzedModel->resolvedTable),
                    context: ['code' => 'TABLE', 'class' => $className, 'table' => $analyzedModel->resolvedTable],
                );
                continue;
            }

            $referencedTables[strtolower($table->name)] = true;

            foreach ($analyzedModel->relations as $decision) {
                if (!$decision->accepted) {
                    continue;
                }

                foreach ($decision->relation->mapping as $targetField => $localField) {
                    if (!$this->tableHasField($table, $localField)) {
                        $diagnostics[] = new Diagnostic(
                            severity: 'warning',
                            message: sprintf('local field `%s` missing in `%s` for relation `%s`', $localField, $table->name, $decision->relation->name),
                            context: ['code' => 'RELATION', 'class' => $className, 'relation' => $decision->relation->name],
                        );
                    }
                    if ($targetField === '') {
                        $diagnostics[] = new Diagnostic(
                            severity: 'warning',
                            message: sprintf('relation `%s` has empty target field mapping', $decision->relation->name),
                            context: ['code' => 'RELATION', 'class' => $className, 'relation' => $decision->relation->name],
                        );
                    }
                }
            }

            foreach ($analyzedModel->model->primaryKey as $pk) {
                if (!$this->tableHasField($table, $pk)) {
                    $diagnostics[] = new Diagnostic(
                        severity: 'warning',
                        message: sprintf('primaryKey `%s` not found in `%s`', $pk, $table->name),
                        context: ['code' => 'PRIMARY_KEY', 'class' => $className, 'field' => $pk],
                    );
                }
            }
        }

        foreach ($schema->tables as $table) {
            if (!isset($referencedTables[strtolower($table->name)])) {
                $diagnostics[] = new Diagnostic(
                    severity: 'info',
                    message: sprintf('table `%s` has no mapped model in class list', $table->name),
                    context: ['code' => 'SCHEMA', 'table' => $table->name],
                );
            }
        }

        return $diagnostics;
    }

    /**
     * @param array<string, ModelIntrospectionSchema> $metas
     * @param list<ModelHierarchySchema> $hierarchies
     * @return array<string, array{table:string, classes:list<string>}>
     */
    private function buildSingleTableInheritanceMap(array $metas, array $hierarchies): array
    {
        $sti = [];
        foreach ($hierarchies as $hierarchy) {
            $table = null;
            $concrete = [];

            foreach ($hierarchy->classes as $className) {
                $meta = $metas[$className] ?? null;
                if ($meta === null) {
                    continue;
                }

                $resolved = $this->resolveTable($meta, $metas);
                if ($resolved === null) {
                    continue;
                }

                if ($table === null) {
                    $table = $resolved;
                }

                if ($resolved !== $table || $meta->isAbstract) {
                    continue;
                }

                $concrete[] = $className;
            }

            if ($table === null || count($concrete) < 2) {
                continue;
            }

            $root = $concrete[0];
            $sti[$root] = ['table' => $table, 'classes' => $concrete];
        }

        return $sti;
    }

    /**
     * @param array<string, ModelIntrospectionSchema> $metas
     */
    public function resolveTable(ModelIntrospectionSchema $meta, array $metas): ?string
    {
        if ($meta->table !== null) {
            return $meta->table;
        }

        $parent = $meta->extends;
        while ($parent !== null && isset($metas[$parent])) {
            $parentMeta = $metas[$parent];
            if ($parentMeta->table !== null) {
                return $parentMeta->table;
            }
            $parent = $parentMeta->extends;
        }

        return null;
    }

    private function tableHasField(TableIntrospectionDto $table, string $fieldName): bool
    {
        $needle = strtolower($fieldName);
        foreach ($table->fields as $field) {
            if (strtolower($field->name) === $needle) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, TableIntrospectionDto>
     */
    private function indexTablesCaseInsensitive(DatabaseIntrospectionDto $schema): array
    {
        $index = [];
        foreach ($schema->tables as $table) {
            $index[strtolower($table->name)] = $table;
        }

        return $index;
    }

    /**
     * @param array<string, TableIntrospectionDto> $schemaTableIndex
     */
    private function findTableByName(array $schemaTableIndex, string $name): ?TableIntrospectionDto
    {
        return $schemaTableIndex[strtolower($name)] ?? null;
    }

    /**
     * @param list<Diagnostic> $diagnostics
     */
    public function renderReport(array $diagnostics): string
    {
        $lines = [];
        $lines[] = '# Mismatch Report';
        $lines[] = 'generated_at=' . date('c');
        $lines[] = 'count=' . count($diagnostics);
        $lines[] = '';

        if ($diagnostics === []) {
            $lines[] = 'No mismatches detected.';
            return implode("\n", $lines) . "\n";
        }

        foreach ($diagnostics as $diagnostic) {
            $context = $diagnostic->context !== [] ? ' | ' . http_build_query($diagnostic->context, '', ',') : '';
            $lines[] = sprintf('- [%s] %s%s', $diagnostic->severity, $diagnostic->message, $context);
        }

        return implode("\n", $lines) . "\n";
    }
}
