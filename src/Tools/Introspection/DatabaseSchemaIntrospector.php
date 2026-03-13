<?php

declare(strict_types=1);

namespace App\Tools\Introspection;

use App\Tools\Database\SchemaDumpParser;
use App\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;

final class DatabaseSchemaIntrospector
{
    public function __construct(private readonly SchemaDumpParser $schemaDumpParser)
    {
    }

    public function introspect(string $schemaPath): DatabaseIntrospectionDto
    {
        return $this->schemaDumpParser->parseFile($schemaPath);
    }
}
