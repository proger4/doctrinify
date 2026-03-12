<?php

declare(strict_types=1);

namespace Doctrinify\Service;

use Doctrinify\Tools\Analysis\PipelineAnalyzer;
use Doctrinify\Tools\Codebase\CodebaseInputLoader;
use Doctrinify\Tools\Codegen\DoctrineXmlCodeGenerator;
use Doctrinify\Tools\Codegen\PhpAccessorCodeGenerator;
use Doctrinify\Tools\Introspection\DatabaseSchemaIntrospector;
use Doctrinify\Tools\Introspection\ModelIntrospector;
use Doctrinify\Tools\Persist\ArtifactPersister;
use Doctrinify\Tools\Schemas\Pipeline\Diagnostic;
use Doctrinify\Tools\Schemas\Pipeline\GeneratedArtifactSchema;
use Doctrinify\Tools\Schemas\Pipeline\GenerationResultSchema;
use Doctrinify\Tools\Schemas\Pipeline\IntrospectionResultSchema;
use Doctrinify\Tools\Config\ConfigLoader;
use Doctrinify\Tools\Database\SchemaDumpParser;
use Doctrinify\Tools\Schemas\DBIntrospection\TableIntrospectionDto;
use Doctrinify\Tools\Schemas\ProjectProfileInterface;
use Doctrinify\Tools\Schemas\YamlProjectProfile;

final class OrmGeneratorService
{
    private string $projectRoot;
    private CodebaseInputLoader $codebaseLoader;
    private ModelIntrospector $modelIntrospector;
    private DatabaseSchemaIntrospector $databaseIntrospector;
    private PipelineAnalyzer $analyzer;
    private DoctrineXmlCodeGenerator $xmlCodeGenerator;
    private PhpAccessorCodeGenerator $phpCodeGenerator;
    private ArtifactPersister $persister;
    private ConfigLoader $configLoader;
    private ?ProjectProfileInterface $projectProfile;

    public function __construct(
        ?string $projectRoot = null,
        ?SchemaDumpParser $schemaDumpParser = null,
        ?CodebaseInputLoader $codebaseLoader = null,
        ?ModelIntrospector $modelIntrospector = null,
        ?DatabaseSchemaIntrospector $databaseIntrospector = null,
        ?PipelineAnalyzer $analyzer = null,
        ?DoctrineXmlCodeGenerator $xmlCodeGenerator = null,
        ?PhpAccessorCodeGenerator $phpCodeGenerator = null,
        ?ArtifactPersister $persister = null,
        ?ConfigLoader $configLoader = null,
        ?ProjectProfileInterface $projectProfile = null,
    ) {
        $this->projectRoot = $projectRoot ?? (getcwd() ?: '.');
        $this->configLoader = $configLoader ?? new ConfigLoader();
        $this->codebaseLoader = $codebaseLoader ?? new CodebaseInputLoader($this->projectRoot, $this->configLoader);
        $this->modelIntrospector = $modelIntrospector ?? new ModelIntrospector();
        $this->databaseIntrospector = $databaseIntrospector ?? new DatabaseSchemaIntrospector($schemaDumpParser ?? new SchemaDumpParser());
        $this->analyzer = $analyzer ?? new PipelineAnalyzer();
        $this->xmlCodeGenerator = $xmlCodeGenerator ?? new DoctrineXmlCodeGenerator();
        $this->phpCodeGenerator = $phpCodeGenerator ?? new PhpAccessorCodeGenerator();
        $this->persister = $persister ?? new ArtifactPersister();
        $this->projectProfile = $projectProfile;
    }

    /**
     * @return array{xml_files:list<string>, php_files:list<string>, mismatch_report:string, warnings:list<string>}
     */
    public function generate(string $configPath): array
    {
        $profile = $this->projectProfile ?? YamlProjectProfile::fromFile($this->projectRoot, $configPath, $this->configLoader);
        $codebase = $this->codebaseLoader->load($configPath, true);

        $models = [];
        foreach ($codebase->classes as $className) {
            if (in_array($className, $codebase->config->baseClasses, true)) {
                continue;
            }

            $short = $this->shortClassName($className);
            $file = $codebase->classFiles[$className] ?? ($codebase->config->modelsPath . '/' . $short . '.php');
            if (!is_file($file)) {
                throw new \RuntimeException(sprintf('Model file not found for %s: %s', $className, $file));
            }

            $models[$className] = $this->modelIntrospector->introspect($className, $file, $codebase->config->useAst);
        }

        $database = $this->databaseIntrospector->introspect($codebase->config->schemaPath);
        $introspection = new IntrospectionResultSchema($models, $database);
        $analysis = $this->analyzer->analyze($introspection);

        $schemaIndex = $this->indexTablesCaseInsensitive($database->tables);
        $artifacts = [];

        foreach ($analysis->models as $className => $analyzedModel) {
            if ($analyzedModel->resolvedTable === null) {
                continue;
            }

            $table = $this->findTableByName($schemaIndex, $analyzedModel->resolvedTable);
            if ($table === null) {
                continue;
            }

            $classDiagnostics = $this->diagnosticsForClass($analysis->diagnostics, $className);

            $xml = null;
            $xmlFilename = null;
            if ($codebase->config->generateXml) {
                $xml = $this->xmlCodeGenerator->generate(
                    className: $className,
                    analyzedModel: $analyzedModel,
                    table: $table,
                    stiByRoot: $analysis->stiByRoot,
                    modelMetas: $models,
                    profile: $profile,
                    diagnostics: $classDiagnostics,
                );
                $xmlFilename = $this->xmlCodeGenerator->buildFilename($className, $profile);
            }

            $php = null;
            $phpFilename = null;
            if ($codebase->config->generatePhp) {
                $php = $this->phpCodeGenerator->generate($className, $table, $profile, $classDiagnostics);
                $phpFilename = $this->phpCodeGenerator->buildFilename($className);
            }

            $artifacts[] = new GeneratedArtifactSchema($className, $xmlFilename, $phpFilename, $xml, $php);
        }

        $reportDiagnostics = array_merge(
            [new Diagnostic('info', sprintf('regeneration strategy: %s', $profile->getRegenerationStrategy()), ['code' => 'REGEN'])],
            $analysis->diagnostics,
        );

        $generation = new GenerationResultSchema(
            artifacts: $artifacts,
            report: $this->analyzer->renderReport($reportDiagnostics),
        );

        $persisted = $this->persister->persist(
            result: $generation,
            xmlOutputPath: $codebase->config->xmlOutputPath,
            phpOutputPath: $codebase->config->phpOutputPath,
            profile: $profile,
        );

        return [
            'xml_files' => $persisted->xmlFiles,
            'php_files' => $persisted->phpFiles,
            'mismatch_report' => $persisted->reportPath,
            'warnings' => $codebase->warnings,
        ];
    }

    public function clean(string $configPath): void
    {
        $codebase = $this->codebaseLoader->load($configPath, false);
        $this->persister->clean($codebase->config->xmlOutputPath, $codebase->config->phpOutputPath);
    }

    /**
     * @param array<string, TableIntrospectionDto> $tables
     */
    private function findTableByName(array $tables, string $name): ?TableIntrospectionDto
    {
        return $tables[strtolower($name)] ?? null;
    }

    /**
     * @param array<string, TableIntrospectionDto> $tables
     * @return array<string, TableIntrospectionDto>
     */
    private function indexTablesCaseInsensitive(array $tables): array
    {
        $index = [];
        foreach ($tables as $table) {
            $index[strtolower($table->name)] = $table;
        }

        return $index;
    }

    private function shortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts) ?: $fqn;
    }

    /**
     * @param list<Diagnostic> $diagnostics
     * @return list<Diagnostic>
     */
    private function diagnosticsForClass(array $diagnostics, string $className): array
    {
        $filtered = [];
        foreach ($diagnostics as $diagnostic) {
            if (($diagnostic->context['class'] ?? null) === $className) {
                $filtered[] = $diagnostic;
            }
        }

        return $filtered;
    }
}
