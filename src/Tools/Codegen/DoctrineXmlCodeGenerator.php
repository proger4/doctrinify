<?php

declare(strict_types=1);

namespace App\Tools\Codegen;

use App\Tools\Schemas\Pipeline\AnalyzedModelSchema;
use App\Tools\Schemas\Pipeline\Diagnostic;
use App\Tools\Schemas\Pipeline\ModelIntrospectionSchema;
use App\Tools\Schemas\DBIntrospection\ColumnIntrospectionDto;
use App\Tools\Schemas\DBIntrospection\TableIntrospectionDto;
use App\Tools\Schemas\ToolingProfileInterface;

final class DoctrineXmlCodeGenerator
{
    /**
     * @param array<string, array{table:string, classes:list<string>}> $stiByRoot
     * @param array<string, ModelIntrospectionSchema> $modelMetas
     * @param list<Diagnostic> $diagnostics
     */
    public function generate(
        string                  $className,
        AnalyzedModelSchema     $analyzedModel,
        TableIntrospectionDto   $table,
        array                   $stiByRoot,
        array                   $modelMetas,
        ToolingProfileInterface $profile,
        array                   $diagnostics,
    ): string {
        $xml = new \SimpleXMLElement('<?xml version="1.0" encoding="UTF-8"?><doctrine-mapping/>');

        $rootAttributes = $profile->getDoctrineXmlRootAttributes();
        $deferredAttributes = [];
        foreach ($rootAttributes as $attr => $value) {
            if (str_contains($attr, ':')) {
                $deferredAttributes[$attr] = $value;
                continue;
            }

            $xml->addAttribute($attr, $value);
        }

        $entity = $xml->addChild('entity');
        $entity->addAttribute('name', $className);
        $entity->addAttribute('table', $analyzedModel->resolvedTable ?? '');

        foreach ($table->primaryKey as $pk) {
            $column = $this->findFieldByName($table, $pk);
            $id = $entity->addChild('id');
            $id->addAttribute('name', $pk);
            $id->addAttribute('type', $this->mapSqlTypeToDoctrineType($column?->type ?? 'VARCHAR(255)'));
            $generator = $id->addChild('generator');
            $generator->addAttribute('strategy', count($table->primaryKey) > 1 ? 'NONE' : 'AUTO');
        }

        foreach ($table->fields as $field) {
            if ($this->containsField($table->primaryKey, $field->name)) {
                continue;
            }

            $xmlField = $entity->addChild('field');
            $xmlField->addAttribute('name', $field->name);
            $xmlField->addAttribute('type', $this->mapSqlTypeToDoctrineType($field->type));
            if ($field->nullable) {
                $xmlField->addAttribute('nullable', 'true');
            }
        }

        foreach ($analyzedModel->relations as $decision) {
            if (!$decision->accepted) {
                continue;
            }

            $relation = $decision->relation;
            if ($relation->kind === 'many-to-one') {
                $assoc = $entity->addChild('many-to-one');
                $assoc->addAttribute('field', $relation->name);
                $assoc->addAttribute('target-entity', $relation->target);
                foreach ($relation->mapping as $targetField => $localField) {
                    $join = $assoc->addChild('join-column');
                    $join->addAttribute('name', $localField);
                    $join->addAttribute('referenced-column-name', $targetField);
                }
                continue;
            }

            $assoc = $entity->addChild('one-to-many');
            $assoc->addAttribute('field', $relation->name);
            $assoc->addAttribute('target-entity', $relation->target);
            $assoc->addAttribute('mapped-by', $this->guessMappedByName($className));
        }

        $rootClass = $this->findStiRootForClass($className, $stiByRoot);
        if ($rootClass !== null) {
            $inherit = $entity->addChild('inheritance-type');
            $inherit->addAttribute('value', 'SINGLE_TABLE');

            if ($rootClass === $className) {
                $dc = $entity->addChild('discriminator-column');
                $dc->addAttribute('name', 'type');
                $dc->addAttribute('type', 'string');

                $map = $entity->addChild('discriminator-map');
                foreach ($stiByRoot[$rootClass]['classes'] as $stiClass) {
                    $entry = $map->addChild('discriminator-mapping');
                    $entry->addAttribute('value', $this->resolveDiscriminatorValue($stiClass, $modelMetas));
                    $entry->addAttribute('class', $stiClass);
                }
            } else {
                $value = $entity->addChild('discriminator-value');
                $value->addAttribute('value', $this->resolveDiscriminatorValue($className, $modelMetas));
            }
        }

        $dom = dom_import_simplexml($xml);
        if (!$dom instanceof \DOMElement) {
            throw new \RuntimeException('Failed to build XML');
        }

        $document = $dom->ownerDocument;
        if (!$document instanceof \DOMDocument) {
            throw new \RuntimeException('Failed to build XML document');
        }

        if ($document->documentElement instanceof \DOMElement) {
            foreach ($deferredAttributes as $attr => $value) {
                $document->documentElement->setAttribute($attr, $value);
            }
        }

        if ($profile->shouldAddGeneratedMarker() && $document->documentElement instanceof \DOMElement) {
            $generatedMarker = sprintf('@generated by %s', $profile->getGenerationNaming());
            $comment = $document->createComment(sprintf(' %s | command=tools:orm:generate | version=dev ', $generatedMarker));
            $document->insertBefore($comment, $document->documentElement);
        }

        if ($profile->shouldEmbedDiagnostics() && $diagnostics !== []) {
            $lines = [];
            foreach ($diagnostics as $diagnostic) {
                $lines[] = sprintf('[%s] %s', $diagnostic->severity, $diagnostic->message);
            }
            $comment = $document->createComment(' diagnostics: ' . implode(' | ', $lines) . ' ');
            if ($document->documentElement instanceof \DOMElement) {
                $document->insertBefore($comment, $document->documentElement);
            }
        }

        $document->formatOutput = true;
        return (string) $document->saveXML();
    }

    public function buildFilename(string $className, ToolingProfileInterface $profile): string
    {
        $pattern = $profile->getDoctrineXmlFilenamePattern();
        return str_replace('{class}', $this->shortClassName($className), $pattern);
    }

    /**
     * @param array<string, array{table:string, classes:list<string>}> $stiByRoot
     */
    private function findStiRootForClass(string $className, array $stiByRoot): ?string
    {
        foreach ($stiByRoot as $root => $info) {
            if (in_array($className, $info['classes'], true)) {
                return $root;
            }
        }

        return null;
    }

    /**
     * @param array<string, ModelIntrospectionSchema> $modelMetas
     */
    private function resolveDiscriminatorValue(string $className, array $modelMetas): string
    {
        $explicit = $modelMetas[$className]->discriminator ?? null;
        if (is_string($explicit) && $explicit !== '') {
            return $explicit;
        }

        return strtolower($this->shortClassName($className));
    }

    private function guessMappedByName(string $className): string
    {
        return lcfirst($this->shortClassName($className));
    }

    private function shortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts) ?: $fqn;
    }

    private function mapSqlTypeToDoctrineType(string $sqlType): string
    {
        $type = strtoupper($sqlType);
        if (str_starts_with($type, 'INT') || str_starts_with($type, 'NUMBER(')) {
            return str_contains($type, ',') ? 'decimal' : 'integer';
        }

        if (str_starts_with($type, 'DECIMAL') || str_starts_with($type, 'NUMERIC')) {
            return 'decimal';
        }

        if (str_starts_with($type, 'DATE') && !str_starts_with($type, 'DATETIME')) {
            return 'date';
        }

        if (str_starts_with($type, 'DATETIME') || str_starts_with($type, 'TIMESTAMP')) {
            return 'datetime';
        }

        return 'string';
    }

    private function findFieldByName(TableIntrospectionDto $table, string $name): ?ColumnIntrospectionDto
    {
        foreach ($table->fields as $field) {
            if (strtolower($field->name) === strtolower($name)) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param list<string> $haystack
     */
    private function containsField(array $haystack, string $needle): bool
    {
        foreach ($haystack as $field) {
            if (strtolower($field) === strtolower($needle)) {
                return true;
            }
        }

        return false;
    }
}
