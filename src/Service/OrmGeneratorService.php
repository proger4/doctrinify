<?php

declare(strict_types=1);

namespace App\Service;

use App\Tools\Analysis\PipelineAnalyzer;
use App\Tools\Analysis\AnalysisBatchBuilder;
use App\Tools\Analysis\GenerationBatchSelector;
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
    private AnalysisBatchBuilder $analysisBatchBuilder;
    private GenerationBatchSelector $generationBatchSelector;
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
        ?AnalysisBatchBuilder       $analysisBatchBuilder = null,
        ?GenerationBatchSelector    $generationBatchSelector = null,
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
        $this->analysisBatchBuilder = $analysisBatchBuilder ?? new AnalysisBatchBuilder();
        $this->generationBatchSelector = $generationBatchSelector ?? new GenerationBatchSelector();
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
        $trace = [];
        $profile = $this->projectProfile ?? YamlToolingProfile::fromFile($configPath, $this->projectRoot);
        $trace[] = sprintf('profile: %s', get_class($profile));
        $codebase = $this->codebaseLoader->load($configPath, true);
        $trace[] = sprintf('codebase: classes=%d files=%d', count($codebase->classes), count($codebase->classFiles));

        $models = [];
        foreach ($codebase->classFiles as $className => $file) {
            if (in_array($className, $codebase->config->baseClasses, true)) {
                continue;
            }
            if (!is_file($file)) {
                throw new \RuntimeException(sprintf('Model file not found for %s: %s', $className, $file));
            }

            $models[$className] = $this->modelIntrospector->introspect($file, ['class' => $className]);
        }
        $trace[] = sprintf('model_introspection: %d models', count($models));

        $database = $this->databaseIntrospector->introspect($codebase->config->schemaPath, []);
        $trace[] = sprintf('db_introspection: tables=%d sequences=%d', count($database->tables), count($database->sequences));
        $introspection = new IntrospectionResultSchema($models, $database, $codebase->hierarchies);
        $analysis = $this->analyzer->analyze($introspection);
        $batches = $this->analysisBatchBuilder->build($analysis->models, $database, $codebase->hierarchies);
        $selectedBatches = $this->generationBatchSelector->select($batches, $codebase->classFiles);
        $trace[] = sprintf('generation_selection: selected=%d from_batches=%d', count($selectedBatches), count($batches));
        $trace[] = sprintf('analysis: diagnostics=%d', count($analysis->diagnostics));

        $artifacts = [];
        $phpMutationTargets = [];

        foreach ($selectedBatches as $batch) {
            $className = $batch->className;
            $analyzedModel = $batch->analyzedModel;
            $table = $batch->table;
            if (!$table instanceof TableIntrospectionDto) {
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

            $phpInstruction = null;
            if ($codebase->config->generatePhp) {
                $phpTargetClass = $this->resolvePhpMutationTargetClass($batch, $codebase->classFiles);
                if (!isset($phpMutationTargets[$phpTargetClass])) {
                    $phpMutationTargets[$phpTargetClass] = true;
                    $modelFile = $codebase->classFiles[$phpTargetClass] ?? ($codebase->config->modelsPath . '/' . $this->shortClassName($phpTargetClass) . '.php');
                    $phpInstruction = $this->phpCodeGenerator->buildAstInstruction(
                        className: $phpTargetClass,
                        targetPath: $modelFile,
                        table: $table,
                        profile: $profile,
                        diagnostics: $classDiagnostics,
                    );
                }
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
        $trace[] = sprintf('persist: xml=%d php=%d', count($persisted->xmlFiles), count($persisted->phpFiles));

        $warnings = $codebase->warnings;
        if ($codebase->config->tracePipeline) {
            foreach ($trace as $step) {
                $warnings[] = '[trace] ' . $step;
            }
        }

        return [
            'xml_files' => $persisted->xmlFiles,
            'php_files' => $persisted->phpFiles,
            'mismatch_report' => $persisted->reportPath,
            'warnings' => $warnings,
        ];
    }

    public function clean(string $configPath): void
    {
        $codebase = $this->codebaseLoader->load($configPath, true);
        $this->persister->clean($codebase->config->xmlOutputPath, '');
        $this->persister->cleanGeneratedAstMembers($codebase->classFiles);
    }

    private function shortClassName(string $fqn): string
    {
        $parts = explode('\\', $fqn);
        return end($parts) ?: $fqn;
    }

    /**
     * @param array<Diagnostic> $diagnostics
     * @return array<Diagnostic>
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

    /**
     * @param array<string, string> $classFiles
     */
    private function resolvePhpMutationTargetClass(\App\Tools\Schemas\Pipeline\AnalysisBatchSchema $batch, array $classFiles): string
    {
        $className = $batch->className;
        $short = strtolower($this->shortClassName($className));

        foreach ($batch->hierarchyClasses as $candidate) {
            if (!is_string($candidate) || strtolower($this->shortClassName($candidate)) !== $short) {
                continue;
            }

            $filePath = $classFiles[$candidate] ?? '';
            $isBaseByNamespace = stripos($candidate, '\\_base\\') !== false;
            $isBaseByPath = is_string($filePath) && preg_match('~(?:^|/)_base(?:/|$)~', strtolower(str_replace('\\', '/', $filePath))) === 1;
            if ($isBaseByNamespace || $isBaseByPath) {
                return $candidate;
            }
        }

        return $className;
    }
}
