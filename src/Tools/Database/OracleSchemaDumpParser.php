<?php

declare(strict_types=1);

namespace App\Tools\Database;

use App\Tools\Schemas\DBIntrospection\ColumnIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\ForeignKeyIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\SequenceIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\TableIntrospectionDto;

final class OracleSchemaDumpParser
{
    public function parseFile(string $path): DatabaseIntrospectionDto
    {
        if (!is_file($path)) {
            throw new \RuntimeException(sprintf('Schema file not found: %s', $path));
        }

        $sql = file_get_contents($path);
        if ($sql === false) {
            throw new \RuntimeException(sprintf('Failed to read schema file: %s', $path));
        }

        return $this->parseSql($sql);
    }

    public function parseSql(string $sql): DatabaseIntrospectionDto
    {
        $cleanSql = $this->stripLineComments($sql);
        $tables = $this->parseCreateTableBlocks($cleanSql);
        $this->parseAlterTableConstraints($cleanSql, $tables);
        $sequences = $this->parseSequences($cleanSql);

        $tableDtos = [];
        foreach ($tables as $tableName => $tableData) {
            $primaryKey = $this->normalizeIdentifierList($tableData['primaryKey']);
            $uniqueConstraints = array_map(
                function (array $constraint): array {
                    return $this->normalizeIdentifierList($constraint);
                },
                $tableData['uniqueConstraints']
            );

            $foreignKeyDtos = [];
            foreach ($tableData['foreignKeys'] as $fkName => $fkData) {
                $columns = $this->normalizeIdentifierList($fkData['columns']);
                $isOneToOne = $this->isOneToOne($columns, $primaryKey, $uniqueConstraints);

                $foreignKeyDtos[$fkName] = new ForeignKeyIntrospectionDto(
                    name: $fkName,
                    columns: $columns,
                    referencedTable: $this->normalizeIdentifier($fkData['referencedTable']),
                    referencedColumns: $this->normalizeIdentifierList($fkData['referencedColumns']),
                    isOneToOne: $isOneToOne,
                );
            }

            $isManyMany = $this->isManyManyTable($primaryKey, $tableData['foreignKeys']);

            $tableDtos[$tableName] = new TableIntrospectionDto(
                name: $tableName,
                fields: $tableData['fields'],
                primaryKey: $primaryKey,
                foreignKeys: $foreignKeyDtos,
                uniqueConstraints: $uniqueConstraints,
                isManyMany: $isManyMany,
            );
        }

        return new DatabaseIntrospectionDto(tables: $tableDtos, sequences: $sequences);
    }

    /**
     * @param array<string, array{fields: array<string, ColumnIntrospectionDto>, primaryKey: list<string>, foreignKeys: array<string, array{columns:list<string>, referencedTable:string, referencedColumns:list<string>}>, uniqueConstraints: list<list<string>>}> $tables
     */
    private function parseAlterTableConstraints(string $sql, array &$tables): void
    {
        preg_match_all(
            '/ALTER\s+TABLE\s+("?[A-Z0-9_$#]+"?)\s+ADD\s+CONSTRAINT\s+("?[A-Z0-9_$#]+"?)\s+(PRIMARY\s+KEY|UNIQUE|FOREIGN\s+KEY)\s*\(([^)]+)\)(?:\s*REFERENCES\s+("?[A-Z0-9_$#]+"?)\s*\(([^)]+)\))?/is',
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $tableName = $this->normalizeIdentifier($match[1]);
            $constraintName = $this->normalizeIdentifier($match[2]);
            $constraintType = strtoupper(preg_replace('/\s+/', ' ', trim($match[3])) ?? '');
            $columns = $this->parseIdentifierList($match[4]);

            if (!isset($tables[$tableName])) {
                $tables[$tableName] = [
                    'fields' => [],
                    'primaryKey' => [],
                    'foreignKeys' => [],
                    'uniqueConstraints' => [],
                ];
            }

            if ($constraintType === 'PRIMARY KEY') {
                $tables[$tableName]['primaryKey'] = $columns;
                continue;
            }

            if ($constraintType === 'UNIQUE') {
                $tables[$tableName]['uniqueConstraints'][] = $columns;
                continue;
            }

            if ($constraintType === 'FOREIGN KEY') {
                $referencedTable = $this->normalizeIdentifier($match[5] ?? '');
                $referencedColumns = $this->parseIdentifierList($match[6] ?? '');
                $tables[$tableName]['foreignKeys'][$constraintName] = [
                    'columns' => $columns,
                    'referencedTable' => $referencedTable,
                    'referencedColumns' => $referencedColumns,
                ];
            }
        }
    }

    /**
     * @return array<string, array{fields: array<string, ColumnIntrospectionDto>, primaryKey: list<string>, foreignKeys: array<string, array{columns:list<string>, referencedTable:string, referencedColumns:list<string>}>, uniqueConstraints: list<list<string>>}>
     */
    private function parseCreateTableBlocks(string $sql): array
    {
        $tables = [];
        preg_match_all('/CREATE\s+TABLE\s+("?[A-Z0-9_$#]+"?)\s*\((.*?)\)\s*;/is', $sql, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            $tableName = $this->normalizeIdentifier($match[1]);
            $body = $match[2];
            $parts = $this->splitTopLevelByComma($body);

            $fields = [];
            $primaryKey = [];
            $foreignKeys = [];
            $uniqueConstraints = [];

            foreach ($parts as $part) {
                $part = trim($part);
                if ($part === '') {
                    continue;
                }

                if (preg_match('/^CONSTRAINT\s+("?[A-Z0-9_$#]+"?)\s+PRIMARY\s+KEY\s*\(([^)]+)\)$/i', $part, $cMatch) === 1) {
                    $primaryKey = $this->parseIdentifierList($cMatch[2]);
                    continue;
                }

                if (preg_match('/^CONSTRAINT\s+("?[A-Z0-9_$#]+"?)\s+UNIQUE\s*\(([^)]+)\)$/i', $part, $cMatch) === 1) {
                    $uniqueConstraints[] = $this->parseIdentifierList($cMatch[2]);
                    continue;
                }

                if (preg_match('/^CONSTRAINT\s+("?[A-Z0-9_$#]+"?)\s+FOREIGN\s+KEY\s*\(([^)]+)\)\s+REFERENCES\s+("?[A-Z0-9_$#]+"?)\s*\(([^)]+)\)$/i', $part, $cMatch) === 1) {
                    $fkName = $this->normalizeIdentifier($cMatch[1]);
                    $foreignKeys[$fkName] = [
                        'columns' => $this->parseIdentifierList($cMatch[2]),
                        'referencedTable' => $this->normalizeIdentifier($cMatch[3]),
                        'referencedColumns' => $this->parseIdentifierList($cMatch[4]),
                    ];
                    continue;
                }

                if (preg_match('/^("?[A-Z0-9_$#]+"?)\s+([A-Z][A-Z0-9_]*(?:\s*\([^)]*\))?)(.*)$/is', $part, $colMatch) !== 1) {
                    continue;
                }

                $columnName = $this->normalizeIdentifier($colMatch[1]);
                $columnType = strtoupper(trim($colMatch[2]));
                $tail = strtoupper($colMatch[3]);

                $fields[$columnName] = new ColumnIntrospectionDto(
                    name: $columnName,
                    type: $columnType,
                    nullable: stripos($tail, 'NOT NULL') === false,
                );

                if (stripos($tail, 'PRIMARY KEY') !== false && !in_array($columnName, $primaryKey, true)) {
                    $primaryKey[] = $columnName;
                }

                if (stripos($tail, ' UNIQUE') !== false) {
                    $uniqueConstraints[] = [$columnName];
                }
            }

            $tables[$tableName] = [
                'fields' => $fields,
                'primaryKey' => $primaryKey,
                'foreignKeys' => $foreignKeys,
                'uniqueConstraints' => $uniqueConstraints,
            ];
        }

        return $tables;
    }

    /**
     * @return array<string, SequenceIntrospectionDto>
     */
    private function parseSequences(string $sql): array
    {
        $sequences = [];
        preg_match_all(
            '/CREATE\s+SEQUENCE\s+("?[A-Z0-9_$#]+"?)(?:\s+START\s+WITH\s+(-?\d+))?(?:\s+INCREMENT\s+BY\s+(-?\d+))?/i',
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $name = $this->normalizeIdentifier($match[1]);
            $startWith = isset($match[2]) && $match[2] !== '' ? (int) $match[2] : null;
            $incrementBy = isset($match[3]) && $match[3] !== '' ? (int) $match[3] : null;
            $sequences[$name] = new SequenceIntrospectionDto($name, $startWith, $incrementBy);
        }

        return $sequences;
    }

    /**
     * @param list<string> $fkColumns
     * @param list<string> $primaryKey
     * @param list<list<string>> $uniqueConstraints
     */
    private function isOneToOne(array $fkColumns, array $primaryKey, array $uniqueConstraints): bool
    {
        if ($this->sameColumnSet($fkColumns, $primaryKey)) {
            return true;
        }

        foreach ($uniqueConstraints as $uniqueConstraint) {
            if ($this->sameColumnSet($fkColumns, $uniqueConstraint)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $primaryKey
     * @param array<string, array{columns:list<string>, referencedTable:string, referencedColumns:list<string>}> $foreignKeys
     */
    private function isManyManyTable(array $primaryKey, array $foreignKeys): bool
    {
        if (count($foreignKeys) !== 2) {
            return false;
        }

        $fkColumns = [];
        foreach ($foreignKeys as $foreignKey) {
            $fkColumns = array_merge($fkColumns, $foreignKey['columns']);
        }

        $fkColumns = array_values(array_unique($this->normalizeIdentifierList($fkColumns)));
        return $this->sameColumnSet($primaryKey, $fkColumns);
    }

    /**
     * @return array<string>
     */
    private function parseIdentifierList(string $raw): array
    {
        $items = $this->splitTopLevelByComma($raw);
        return $this->normalizeIdentifierList($items);
    }

    /**
     * @param list<string> $items
     * @return array<string>
     */
    private function normalizeIdentifierList(array $items): array
    {
        $normalized = [];
        foreach ($items as $item) {
            $id = $this->normalizeIdentifier($item);
            if ($id !== '' && !in_array($id, $normalized, true)) {
                $normalized[] = $id;
            }
        }

        return $normalized;
    }

    private function normalizeIdentifier(string $identifier): string
    {
        $trimmed = trim($identifier);
        if ($trimmed === '') {
            return '';
        }

        if (str_starts_with($trimmed, '"') && str_ends_with($trimmed, '"')) {
            $trimmed = substr($trimmed, 1, -1);
        }

        return strtoupper($trimmed);
    }

    /**
     * @return array<string>
     */
    private function splitTopLevelByComma(string $value): array
    {
        $parts = [];
        $current = '';
        $level = 0;
        $inSingleQuote = false;
        $inDoubleQuote = false;
        $length = strlen($value);

        for ($i = 0; $i < $length; $i++) {
            $char = $value[$i];

            if ($char === "'" && !$inDoubleQuote) {
                $inSingleQuote = !$inSingleQuote;
                $current .= $char;
                continue;
            }

            if ($char === '"' && !$inSingleQuote) {
                $inDoubleQuote = !$inDoubleQuote;
                $current .= $char;
                continue;
            }

            if (!$inSingleQuote && !$inDoubleQuote) {
                if ($char === '(') {
                    $level++;
                } elseif ($char === ')') {
                    $level = max(0, $level - 1);
                } elseif ($char === ',' && $level === 0) {
                    $parts[] = trim($current);
                    $current = '';
                    continue;
                }
            }

            $current .= $char;
        }

        if (trim($current) !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function sameColumnSet(array $left, array $right): bool
    {
        $left = $this->normalizeIdentifierList($left);
        $right = $this->normalizeIdentifierList($right);
        sort($left);
        sort($right);
        return $left === $right;
    }

    private function stripLineComments(string $sql): string
    {
        return (string) preg_replace('/^\s*--.*$/m', '', $sql);
    }
}
