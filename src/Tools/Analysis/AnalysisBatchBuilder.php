<?php

declare(strict_types=1);

namespace App\Tools\Analysis;

use App\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\SequenceIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\TableIntrospectionDto;
use App\Tools\Schemas\Pipeline\AnalysisBatchSchema;
use App\Tools\Schemas\Pipeline\AnalyzedModelSchema;
use App\Tools\Schemas\Pipeline\ModelHierarchySchema;

final class AnalysisBatchBuilder
{
    /**
     * @param array<string, AnalyzedModelSchema> $analyzedModels
     * @param array<ModelHierarchySchema> $hierarchies
     * @return array<int, AnalysisBatchSchema>
     */
    public function build(array $analyzedModels, DatabaseIntrospectionDto $database, array $hierarchies): array
    {
        $schemaIndex = $this->indexTablesCaseInsensitive($database);
        $hierarchyByClass = $this->hierarchyMapByClass($hierarchies);
        $sequenceMap = $this->sequencesByTable($database);

        $batches = [];
        foreach ($analyzedModels as $className => $analyzedModel) {
            $table = null;
            $tableKey = '';
            if (is_string($analyzedModel->resolvedTable) && $analyzedModel->resolvedTable !== '') {
                $tableKey = strtolower($analyzedModel->resolvedTable);
                $table = $schemaIndex[$tableKey] ?? null;
            }

            $batches[] = new AnalysisBatchSchema(
                className: $className,
                analyzedModel: $analyzedModel,
                table: $table,
                hierarchyClasses: $hierarchyByClass[$className] ?? [$className],
                sequences: $tableKey !== '' ? ($sequenceMap[$tableKey] ?? []) : [],
            );
        }

        return $batches;
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
     * @param array<ModelHierarchySchema> $hierarchies
     * @return array<string, array<int, string>>
     */
    private function hierarchyMapByClass(array $hierarchies): array
    {
        $map = [];
        foreach ($hierarchies as $hierarchy) {
            foreach ($hierarchy->classes as $className) {
                $map[$className] = $hierarchy->classes;
            }
        }

        return $map;
    }

    /**
     * @return array<string, array<string, SequenceIntrospectionDto>>
     */
    private function sequencesByTable(DatabaseIntrospectionDto $schema): array
    {
        $grouped = [];
        foreach ($schema->tables as $tableName => $_table) {
            $key = strtolower($tableName);
            $normalized = str_replace('_', '', $key);
            $grouped[$key] = [];
            foreach ($schema->sequences as $sequenceName => $sequence) {
                $seqKey = strtolower($sequenceName);
                if (str_contains($seqKey, $key) || str_contains($seqKey, $normalized)) {
                    $grouped[$key][$sequenceName] = $sequence;
                }
            }
        }

        return $grouped;
    }
}

