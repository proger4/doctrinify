<?php

declare(strict_types=1);

namespace App\Tools\Database;

use App\Tools\Schemas\DBIntrospection\ColumnIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\ForeignKeyIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\TableIntrospectionDto;

final class SchemaDumpParser
{
    private OracleSchemaDumpParser $oracleParser;

    public function __construct(?OracleSchemaDumpParser $oracleParser = null)
    {
        $this->oracleParser = $oracleParser ?? new OracleSchemaDumpParser();
    }

    public function parseFile(string $schemaPath): DatabaseIntrospectionDto
    {
        if (!is_file($schemaPath)) {
            throw new \RuntimeException(sprintf('Schema file not found: %s', $schemaPath));
        }

        $sql = file_get_contents($schemaPath);
        if ($sql === false) {
            throw new \RuntimeException(sprintf('Failed to read schema file: %s', $schemaPath));
        }

        return $this->parseSql($sql);
    }

    public function parseSql(string $sql): DatabaseIntrospectionDto
    {
        if ($this->looksLikeOracleDump($sql)) {
            $parsed = $this->oracleParser->parseSql($sql);
            $this->assertParsedTables($sql, $parsed);
            return $parsed;
        }

        $parsed = $this->parseMysqlStyleSql($sql);
        $this->assertParsedTables($sql, $parsed);
        return $parsed;
    }

    private function looksLikeOracleDump(string $sql): bool
    {
        $upper = strtoupper($sql);
        return str_contains($upper, 'VARCHAR2(')
            || str_contains($upper, 'CREATE SEQUENCE')
            || preg_match('/CREATE\s+TABLE\s+"[A-Z0-9_$#]+"/', $upper) === 1;
    }

    private function parseMysqlStyleSql(string $sql): DatabaseIntrospectionDto
    {
        $tables = [];
        preg_match_all(
            '/CREATE\s+TABLE\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s*\((.*?)\)\s*ENGINE/si',
            $sql,
            $matches,
            PREG_SET_ORDER
        );

        foreach ($matches as $match) {
            $tableName = $match[1];
            $body = $match[2];
            $lines = preg_split('/\r?\n/', $body) ?: [];

            $fields = [];
            $primaryKey = [];
            $foreignKeys = [];
            $uniqueConstraints = [];
            $fkCounter = 0;

            foreach ($lines as $line) {
                $line = trim(trim($line), ',');
                if ($line === '' || str_starts_with($line, '--')) {
                    continue;
                }

                if (preg_match('/^PRIMARY KEY\s*\(([^)]+)\)$/i', $line, $pkMatch) === 1) {
                    $primaryKey = $this->parseIdentifierList($pkMatch[1]);
                    continue;
                }

                if (preg_match('/^UNIQUE(?:\s+KEY)?(?:\s+`?([a-zA-Z0-9_]+)`?)?\s*\(([^)]+)\)$/i', $line, $uqMatch) === 1) {
                    $uniqueConstraints[] = $this->parseIdentifierList($uqMatch[2]);
                    continue;
                }

                if (preg_match('/^FOREIGN KEY\s*\(([^)]+)\)\s*REFERENCES\s+`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s*\(([^)]+)\)/i', $line, $fkMatch) === 1) {
                    $fkCounter++;
                    $fkName = sprintf('FK_%s_%d', strtoupper($tableName), $fkCounter);
                    $columns = $this->parseIdentifierList($fkMatch[1]);
                    $isOneToOne = $this->isOneToOne($columns, $primaryKey, $uniqueConstraints);
                    $foreignKeys[$fkName] = new ForeignKeyIntrospectionDto(
                        name: $fkName,
                        columns: $columns,
                        referencedTable: $fkMatch[2],
                        referencedColumns: $this->parseIdentifierList($fkMatch[3]),
                        isOneToOne: $isOneToOne,
                    );
                    continue;
                }

                if (preg_match('/^`?([a-zA-Z_][a-zA-Z0-9_]*)`?\s+([A-Z]+(?:\([0-9,\s]+\))?)/i', $line, $colMatch) !== 1) {
                    continue;
                }

                $columnName = $colMatch[1];
                $sqlType = strtoupper($colMatch[2]);
                $fields[$columnName] = new ColumnIntrospectionDto(
                    name: $columnName,
                    type: $sqlType,
                    nullable: stripos($line, 'NOT NULL') === false
                );

                if (stripos($line, 'PRIMARY KEY') !== false && !in_array($columnName, $primaryKey, true)) {
                    $primaryKey[] = $columnName;
                }

                if (stripos($line, 'UNIQUE') !== false) {
                    $uniqueConstraints[] = [$columnName];
                }
            }

            $tables[$tableName] = new TableIntrospectionDto(
                name: $tableName,
                fields: $fields,
                primaryKey: $primaryKey,
                foreignKeys: $foreignKeys,
                uniqueConstraints: $uniqueConstraints,
                isManyMany: $this->isManyManyTable($primaryKey, $foreignKeys),
            );
        }

        return new DatabaseIntrospectionDto($tables, []);
    }

    /**
     * @return list<string>
     */
    private function parseIdentifierList(string $raw): array
    {
        return array_values(array_filter(array_map(
            static fn (string $chunk): string => trim($chunk, " \t\n\r\0\x0B`\""),
            explode(',', $raw)
        )));
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
     * @param array<string, ForeignKeyIntrospectionDto> $foreignKeys
     */
    private function isManyManyTable(array $primaryKey, array $foreignKeys): bool
    {
        if (count($foreignKeys) !== 2) {
            return false;
        }

        $fkColumns = [];
        foreach ($foreignKeys as $foreignKey) {
            $fkColumns = array_merge($fkColumns, $foreignKey->columns);
        }

        $fkColumns = array_values(array_unique($fkColumns));
        return $this->sameColumnSet($primaryKey, $fkColumns);
    }

    /**
     * @param list<string> $left
     * @param list<string> $right
     */
    private function sameColumnSet(array $left, array $right): bool
    {
        sort($left);
        sort($right);
        return $left === $right;
    }

    private function assertParsedTables(string $sql, DatabaseIntrospectionDto $parsed): void
    {
        if (trim($sql) === '') {
            return;
        }

        if ($parsed->tables !== []) {
            return;
        }

        throw new \RuntimeException('Schema parser found zero tables. Check SQL format or parser mode.');
    }
}
