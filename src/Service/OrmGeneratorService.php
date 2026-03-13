<?php

declare(strict_types=1);

namespace App\Service;

use App\Tools\Analysis\PipelineAnalyzer;
use App\Tools\Codebase\CodebaseInputLoader;
use App\Tools\Codegen\DoctrineXmlCodeGenerator;
use App\Tools\Codegen\PhpAccessorCodeGenerator;
use App\Tools\Introspection\DatabaseSchemaIntrospector;
use App\Tools\Introspection\ModelIntrospector;
use App\Tools\Persist\ArtifactPersister;
use App\Tools\Schemas\Pipeline\Diagnostic;
use App\Tools\Schemas\Pipeline\GeneratedArtifactSchema;
use App\Tools\Schemas\Pipeline\GenerationResultSchema;
use App\Tools\Schemas\Pipeline\IntrospectionResultSchema;
use App\Tools\Config\ConfigLoader;
use App\Tools\Database\SchemaDumpParser;
use App\Tools\Schemas\DBIntrospection\TableIntrospectionDto;
use App\Tools\Schemas\ToolingProfileInterface;
use App\Tools\Schemas\YamlToolingProfile;

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
    private ?ToolingProfileInterface $projectProfile;

    public function __construct(
        ?string                     $projectRoot = null,
        ?SchemaDumpParser           $schemaDumpParser = null,
        ?CodebaseInputLoader        $codebaseLoader = null,
        ?ModelIntrospector          $modelIntrospector = null,
        ?DatabaseSchemaIntrospector $databaseIntrospector = null,
        ?PipelineAnalyzer           $analyzer = null,
        ?DoctrineXmlCodeGenerator   $xmlCodeGenerator = null,
        ?PhpAccessorCodeGenerator   $phpCodeGenerator = null,
        ?ArtifactPersister          $persister = null,
        ?ConfigLoader               $configLoader = null,
        ?ToolingProfileInterface    $projectProfile = null,
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
        $profile = $this->projectProfile ?? YamlToolingProfile::fromFile($configPath, $this->projectRoot);
        $codebase = $this->codebaseLoader->load($configPath, true);

        $models = [];
        foreach ($codebase->classFiles as $className => $file) {
            if (in_array($className, $codebase->config->baseClasses, true)) {
                continue;
            }
            if (!is_file($file)) {
                throw new \RuntimeException(sprintf('Model file not found for %s: %s', $className, $file));
            }

            $models[$className] = $this->modelIntrospector->introspect($className, $file);
        }

        $database = $this->databaseIntrospector->introspect($codebase->config->schemaPath);
        $introspection = new IntrospectionResultSchema($models, $database, $codebase->hierarchies);
        $analysis = $this->analyzer->analyze($introspection);

        $schemaIndex = $this->indexTablesCaseInsensitive($database->tables);
        $artifacts = [];

        foreach ($codebase->classes as $className) {
            $analyzedModel = $analysis->models[$className] ?? null;
            if ($analyzedModel === null || $analyzedModel->model->isAbstract) {
                continue;
            }

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
            $phpInstruction = null;
            if ($codebase->config->generatePhp) {
                $modelFile = $codebase->classFiles[$className] ?? ($codebase->config->modelsPath . '/' . $this->shortClassName($className) . '.php');
                $phpInstruction = $this->phpCodeGenerator->buildAstInstruction(
                    className: $className,
                    targetPath: $modelFile,
                    table: $table,
                    profile: $profile,
                    diagnostics: $classDiagnostics,
                );
            }

            $artifacts[] = new GeneratedArtifactSchema(
                className: $className,
                xmlFilename: $xmlFilename,
                xml: $xml,
                phpInstruction: $phpInstruction,
            );
        }

        $generation = new GenerationResultSchema(
            artifacts: $artifacts,
            report: $this->analyzer->renderReport($analysis->diagnostics),
        );

        $persisted = $this->persister->persist(
            result: $generation,
            xmlOutputPath: $codebase->config->xmlOutputPath,
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
        $codebase = $this->codebaseLoader->load($configPath, true);
        $this->persister->clean($codebase->config->xmlOutputPath, '');
        $this->persister->cleanGeneratedAstMembers($codebase->classFiles);
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
