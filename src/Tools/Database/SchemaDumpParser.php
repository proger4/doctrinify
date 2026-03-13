<?php

declare(strict_types=1);

namespace App\Tools\Database;

use App\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;

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
        $parsed = $this->oracleParser->parseSql($sql);
        $this->assertParsedTables($sql, $parsed);
        return $parsed;
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
