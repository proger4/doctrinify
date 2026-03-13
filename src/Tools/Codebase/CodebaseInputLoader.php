<?php

declare(strict_types=1);

namespace App\Tools\Codebase;

use App\Tools\AST\AstFacade;
use App\Tools\Config\ConfigLoader;
use App\Tools\Schemas\Pipeline\CodebaseInput;
use App\Tools\Schemas\Pipeline\GeneratorConfig;
use App\Tools\Schemas\Pipeline\ModelHierarchySchema;
use Tree\Node\Node;

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

        if (!isset($config['models_path'], $config['doctrine_xml_path'])) {
            throw new \RuntimeException('Invalid config: missing required paths');
        }

        if (!is_string($config['models_path']) || $config['models_path'] === '') {
            throw new \RuntimeException('Invalid config: "models_path" must be a non-empty string');
        }
        if (!is_string($config['doctrine_xml_path']) || $config['doctrine_xml_path'] === '') {
            throw new \RuntimeException('Invalid config: "doctrine_xml_path" must be a non-empty string');
        }

        $modelsPath = $loader->resolvePath($this->projectRoot, $config['models_path']);
        $classListPath = $this->resolveClassListPath($loader, $resolvedConfigPath, $config);
        $flags = is_array($config['flags'] ?? null) ? $config['flags'] : [];
        $baseClasses = array_values(array_filter(array_map(
            function (string $v): string {
                return $this->normalizeClassPattern($v);
            },
            array_map('strval', is_array($config['base_classes'] ?? null) ? $config['base_classes'] : [])
        )));
        $blacklist = array_values(array_filter(array_map(
            function (string $v): string {
                return $this->normalizeClassPattern($v);
            },
            array_map('strval', is_array($config['blacklist'] ?? null) ? $config['blacklist'] : [])
        )));

        $defaultExcluded = ['vendor', 'tests', 'runtime'];
        $scanExcluded = is_array($config['model_scan_exclude_dirs'] ?? null)
            ? array_values(array_filter(array_map('strval', $config['model_scan_exclude_dirs'])))
            : $defaultExcluded;

        $defaultSchemaPath = dirname($resolvedConfigPath) . '/database/schema.sql';
        $schemaPathConfig = $config['schema_path'] ?? $defaultSchemaPath;
        $schemaPath = $loader->resolvePath(
            $this->projectRoot,
            is_string($schemaPathConfig) && $schemaPathConfig !== '' ? $schemaPathConfig : $defaultSchemaPath
        );

        $classes = [];
        $warnings = [];
        $fileIndex = $this->scanPhpClasses($modelsPath, $scanExcluded);

        if ($requireClassList) {
            if (is_file($classListPath)) {
                $classes = $this->loadClassList($classListPath);
            } else {
                $warnings[] = sprintf('classlist not found (%s), fallback to models_path autoscan', $classListPath);
                $classes = $this->autoDiscoverClasses($fileIndex, $baseClasses, $blacklist);
            }
        }
        $classes = $this->sanitizeCandidateClasses($classes, $baseClasses, $blacklist);

        $analysisClasses = $this->expandWithAncestors($classes, $fileIndex, $baseClasses, $blacklist);
        $classFiles = [];
        foreach ($analysisClasses as $className) {
            if (isset($fileIndex[$className])) {
                $classFiles[$className] = $fileIndex[$className]['file'];
            }
        }

        $hierarchies = $this->buildHierarchies($analysisClasses, $fileIndex);

        return new CodebaseInput(
            config: new GeneratorConfig(
                modelsPath: $modelsPath,
                xmlOutputPath: $loader->resolvePath($this->projectRoot, $config['doctrine_xml_path']),
                schemaPath: $schemaPath,
                classListPath: $classListPath,
                generateXml: (bool) ($flags['generate_doctrine_xml'] ?? true),
                generatePhp: (bool) ($flags['generate_php_accessors'] ?? true),
                baseClasses: $baseClasses,
                blacklist: $blacklist,
                modelScanExcludeDirs: $scanExcluded,
            ),
            classes: $classes,
            classFiles: $classFiles,
            warnings: $warnings,
            hierarchies: $hierarchies,
        );
    }

    /**
     * @param list<string> $classes
     * @param list<string> $baseClasses
     * @param list<string> $blacklist
     * @return array<string>
     */
    private function sanitizeCandidateClasses(array $classes, array $baseClasses, array $blacklist): array
    {
        $baseLookup = array_fill_keys($baseClasses, true);
        $filtered = [];
        foreach ($classes as $className) {
            $name = trim($className);
            if ($name === '') {
                continue;
            }

            if (isset($baseLookup[$name]) || $this->matchesWildcardBlacklist($name, $blacklist)) {
                continue;
            }

            $filtered[$name] = true;
        }

        $out = array_keys($filtered);
        sort($out);
        return $out;
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
     * @return array<string, array{file:string,extends:?string,methodNames:list<string>,isAbstract:bool}>
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
                static function ($current) use ($excludeLookup): bool {
                    if ($current instanceof \SplFileInfo && $current->isDir()) {
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
                'isAbstract' => $parsed['isAbstract'],
            ];
        }

        ksort($index);
        return $index;
    }

    /**
     * @return array{fqn:string,extends:?string,methodNames:list<string>,isAbstract:bool}|null
     */
    private function parsePhpClassHeader(string $filePath): ?array
    {
        $ast = $this->astFacade ?? new AstFacade();
        return $ast->extractFirstClassInfo($filePath);
    }

    /**
     * @param array<string, array{file:string,extends:?string,methodNames:list<string>,isAbstract:bool}> $fileIndex
     * @param list<string> $baseClasses
     * @param list<string> $blacklist
     * @return array<string>
     */
    private function autoDiscoverClasses(array $fileIndex, array $baseClasses, array $blacklist): array
    {
        $classes = [];
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
                    continue;
                }

                $parts = explode('\\', $extends);
                $extendsShort = end($parts) ?: $extends;
                if (isset($baseShortLookup[$extendsShort])) {
                    $classes[] = $fqn;
                    continue;
                }
            }

            if ($ast->hasModelHeuristics($meta['methodNames'])) {
                $classes[] = $fqn;
            }
        }

        sort($classes);
        return $classes;
    }

    /**
     * @param list<string> $classes
     * @param array<string, array{file:string,extends:?string,methodNames:list<string>,isAbstract:bool}> $fileIndex
     * @param list<string> $baseClasses
     * @param list<string> $blacklist
     * @return array<string>
     */
    private function expandWithAncestors(array $classes, array $fileIndex, array $baseClasses, array $blacklist): array
    {
        $lookup = array_fill_keys($classes, true);
        $baseLookup = array_fill_keys($baseClasses, true);

        $stack = $classes;
        while ($stack !== []) {
            $className = array_pop($stack);
            if (!is_string($className) || !isset($fileIndex[$className])) {
                continue;
            }

            $parent = $fileIndex[$className]['extends'];
            if (!is_string($parent) || $parent === '') {
                continue;
            }

            if (isset($baseLookup[$parent]) || $this->matchesWildcardBlacklist($parent, $blacklist)) {
                continue;
            }

            if (isset($fileIndex[$parent]) && !isset($lookup[$parent])) {
                $lookup[$parent] = true;
                $stack[] = $parent;
            }
        }

        $out = array_keys($lookup);
        sort($out);
        return $out;
    }

    /**
     * @param list<string> $classes
     * @param array<string, array{file:string,extends:?string,methodNames:list<string>,isAbstract:bool}> $fileIndex
     * @return array<ModelHierarchySchema>
     */
    private function buildHierarchies(array $classes, array $fileIndex): array
    {
        if ($classes === []) {
            return [];
        }

        $classLookup = array_fill_keys($classes, true);
        /** @var array<string, Node<string>> $nodes */
        $nodes = [];
        foreach ($classes as $className) {
            $nodes[$className] = new Node($className);
        }

        $roots = [];
        foreach ($classes as $className) {
            $parent = $fileIndex[$className]['extends'] ?? null;
            if (is_string($parent) && $parent !== '' && isset($classLookup[$parent])) {
                $nodes[$parent]->addChild($nodes[$className]);
                continue;
            }

            $roots[] = $className;
        }

        sort($roots);
        $hierarchies = [];
        foreach ($roots as $rootClass) {
            $ordered = [];
            $this->collectPreOrder($nodes[$rootClass], $ordered);
            $hierarchies[] = new ModelHierarchySchema($rootClass, $ordered);
        }

        return $hierarchies;
    }

    /**
     * @param Node<string> $node
     * @param list<string> $ordered
     */
    private function collectPreOrder(Node $node, array &$ordered): void
    {
        $value = $node->getValue();
        if (is_string($value) && $value !== '') {
            $ordered[] = $value;
        }

        foreach ($node->getChildren() as $child) {
            if ($child instanceof Node) {
                $this->collectPreOrder($child, $ordered);
            }
        }
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

    private function normalizeClassPattern(string $value): string
    {
        $trimmed = trim($value);
        while (str_contains($trimmed, '\\\\')) {
            $trimmed = str_replace('\\\\', '\\', $trimmed);
        }

        return $trimmed;
    }

    /**
     * @return array<string>
     */
    private function loadClassList(string $classListPath): array
    {
        $lines = file($classListPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            return [];
        }

        return array_values(array_filter(array_map(
            static function (string $line): string {
                return trim($line);
            },
            $lines
        )));
    }
}
