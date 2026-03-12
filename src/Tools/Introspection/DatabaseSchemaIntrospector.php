<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Introspection;

use Doctrinify\Tools\Database\SchemaDumpParser;
use Doctrinify\Tools\Schemas\DBIntrospection\DatabaseIntrospectionDto;

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
