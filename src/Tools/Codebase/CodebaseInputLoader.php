<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Codebase;

use Doctrinify\Tools\AST\AstFacade;
use Doctrinify\Tools\Config\ConfigLoader;
use Doctrinify\Tools\Schemas\Pipeline\CodebaseInput;
use Doctrinify\Tools\Schemas\Pipeline\GeneratorConfig;

final class CodebaseInputLoader
{
    public function __construct(
        private readonly string $projectRoot,
        private readonly ?ConfigLoader $configLoader = null,
        private readonly ?AstFacade $astFacade = null,
    ) {
    }

    public function load(string $configPath, bool $requireClassList = true): CodebaseInput
    {
        $loader = $this->configLoader ?? new ConfigLoader();
        $resolvedConfigPath = $loader->resolvePath($this->projectRoot, $configPath);
        $config = $loader->load($this->projectRoot, $configPath);

        if (!isset($config['models_path'], $config['doctrine_xml_path'], $config['generated_php_path'])) {
            throw new \RuntimeException('Invalid config: missing required paths');
        }

        $modelsPath = $loader->resolvePath($this->projectRoot, (string) $config['models_path']);
        $classListPath = $this->resolveClassListPath($loader, $resolvedConfigPath, $config);
        $flags = is_array($config['flags'] ?? null) ? $config['flags'] : [];
        $baseClasses = array_values(array_filter(array_map('strval', is_array($config['base_classes'] ?? null) ? $config['base_classes'] : [])));
        $blacklist = array_values(array_filter(array_map('strval', is_array($config['blacklist'] ?? null) ? $config['blacklist'] : [])));

        $defaultExcluded = ['vendor', 'tests', 'runtime'];
        $scanExcluded = is_array($config['model_scan_exclude_dirs'] ?? null)
            ? array_values(array_filter(array_map('strval', $config['model_scan_exclude_dirs'])))
            : $defaultExcluded;

        $schemaPath = $loader->resolvePath(
            $this->projectRoot,
            (string) ($config['schema_path'] ?? (dirname($resolvedConfigPath) . '/database/schema.sql'))
        );

        $classes = [];
        $classFiles = [];
        $warnings = [];

        if ($requireClassList) {
            $fileIndex = $this->scanPhpClasses($modelsPath, $scanExcluded);

            if (is_file($classListPath)) {
                $classes = $this->loadClassList($classListPath);
                foreach ($classes as $className) {
                    if (isset($fileIndex[$className])) {
                        $classFiles[$className] = $fileIndex[$className]['file'];
                    }
                }
            } else {
                $warnings[] = sprintf('classlist not found (%s), fallback to models_path autoscan', $classListPath);
                [$classes, $classFiles] = $this->autoDiscoverClasses($fileIndex, $baseClasses, $blacklist);
            }
        }

        return new CodebaseInput(
            config: new GeneratorConfig(
                modelsPath: $modelsPath,
                xmlOutputPath: $loader->resolvePath($this->projectRoot, (string) $config['doctrine_xml_path']),
                phpOutputPath: $loader->resolvePath($this->projectRoot, (string) $config['generated_php_path']),
                schemaPath: $schemaPath,
                classListPath: $classListPath,
                useAst: (bool) ($flags['use_ast_parsing'] ?? true),
                generateXml: (bool) ($flags['generate_doctrine_xml'] ?? true),
                generatePhp: (bool) ($flags['generate_php_accessors'] ?? true),
                baseClasses: $baseClasses,
                blacklist: $blacklist,
                modelScanExcludeDirs: $scanExcluded,
            ),
            classes: $classes,
            classFiles: $classFiles,
            warnings: $warnings,
        );
    }

    /**
     * @param array<string, mixed> $config
     */
    private function resolveClassListPath(ConfigLoader $loader, string $resolvedConfigPath, array $config): string
    {
        $configured = $config['classlist_path'] ?? $config['class_list_path'] ?? null;
        if (is_string($configured) && trim($configured) !== '') {
            return $loader->resolvePath($this->projectRoot, $configured);
        }

        return dirname($resolvedConfigPath) . '/classlist.txt';
    }

    /**
     * @param list<string> $excludeDirs
     * @return array<string, array{file:string,extends:?string,methodNames:list<string>}>
     */
    private function scanPhpClasses(string $modelsPath, array $excludeDirs): array
    {
        if (!is_dir($modelsPath)) {
            throw new \RuntimeException(sprintf('Models path not found: %s', $modelsPath));
        }

        $excludeLookup = array_fill_keys(array_map('strtolower', $excludeDirs), true);
        $index = [];

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveCallbackFilterIterator(
                new \RecursiveDirectoryIterator($modelsPath, \FilesystemIterator::SKIP_DOTS),
                static function (\SplFileInfo $current) use ($excludeLookup): bool {
                    if ($current->isDir()) {
                        return !isset($excludeLookup[strtolower($current->getBasename())]);
                    }

                    return true;
                }
            )
        );

        foreach ($iterator as $fileInfo) {
            if (!$fileInfo instanceof \SplFileInfo || !$fileInfo->isFile()) {
                continue;
            }

            if (strtolower($fileInfo->getExtension()) !== 'php') {
                continue;
            }

            $parsed = $this->parsePhpClassHeader($fileInfo->getPathname());
            if ($parsed === null) {
                continue;
            }

            $index[$parsed['fqn']] = [
                'file' => $fileInfo->getPathname(),
                'extends' => $parsed['extends'],
                'methodNames' => $parsed['methodNames'],
            ];
        }

        ksort($index);
        return $index;
    }

    /**
     * @return array{fqn:string,extends:?string,methodNames:list<string>}|null
     */
    private function parsePhpClassHeader(string $filePath): ?array
    {
        $ast = $this->astFacade ?? new AstFacade();
        return $ast->extractFirstClassInfo($filePath);
    }

    /**
     * @param array<string, array{file:string,extends:?string,methodNames:list<string>}> $fileIndex
     * @param list<string> $baseClasses
     * @param list<string> $blacklist
     * @return array{0:list<string>,1:array<string,string>}
     */
    private function autoDiscoverClasses(array $fileIndex, array $baseClasses, array $blacklist): array
    {
        $classes = [];
        $classFiles = [];
        $ast = $this->astFacade ?? new AstFacade();

        $baseLookup = array_fill_keys($baseClasses, true);
        $baseShortLookup = [];
        foreach ($baseClasses as $baseClass) {
            $parts = explode('\\', $baseClass);
            $baseShortLookup[end($parts) ?: $baseClass] = true;
        }

        foreach ($fileIndex as $fqn => $meta) {
            if (isset($baseLookup[$fqn])) {
                continue;
            }

            if ($this->matchesWildcardBlacklist($fqn, $blacklist)) {
                continue;
            }

            $extends = $meta['extends'];
            if (is_string($extends) && $extends !== '') {
                if (isset($baseLookup[$extends])) {
                    $classes[] = $fqn;
                    $classFiles[$fqn] = $meta['file'];
                    continue;
                }

                $parts = explode('\\', $extends);
                $extendsShort = end($parts) ?: $extends;
                if (isset($baseShortLookup[$extendsShort])) {
                    $classes[] = $fqn;
                    $classFiles[$fqn] = $meta['file'];
                    continue;
                }
            }

            if ($ast->hasModelHeuristics($meta['methodNames'])) {
                $classes[] = $fqn;
                $classFiles[$fqn] = $meta['file'];
            }
        }

        sort($classes);
        return [$classes, $classFiles];
    }

    /**
     * @param list<string> $blacklist
     */
    private function matchesWildcardBlacklist(string $className, array $blacklist): bool
    {
        foreach ($blacklist as $pattern) {
            if (fnmatch($pattern, $className, FNM_NOESCAPE)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<string>
     */
    private function loadClassList(string $classListPath): array
    {
        $lines = file($classListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        return array_values(array_filter(array_map(static fn (string $line): string => trim($line), $lines)));
    }
}
