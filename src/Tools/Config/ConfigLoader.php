<?php

declare(strict_types=1);

namespace App\Tools\Config;

use Symfony\Component\Yaml\Yaml;

final class ConfigLoader
{
    /**
     * @return array<string, mixed>
     */
    public function load(string $projectRoot, string $configPath): array
    {
        $resolved = $this->resolvePath($projectRoot, $configPath);
        if (!is_file($resolved)) {
            throw new \RuntimeException(sprintf('Config file not found: %s', $resolved));
        }

        /** @var array<string, mixed> $config */
        $config = Yaml::parseFile($resolved);
        return $config;
    }

    public function resolvePath(string $projectRoot, string $path): string
    {
        return (new PathResolver($projectRoot))->resolve($path);
    }

    /**
     * @param array<string, mixed> $config
     */
    public function resolvePathWithConfig(string $projectRoot, string $path, array $config): string
    {
        return (new PathResolver($projectRoot, $this->extractAliases($config)))->resolve($path);
    }

    /**
     * @param array<string, mixed> $config
     * @return array<string, string>
     */
    public function extractAliases(array $config): array
    {
        $aliases = [
            'tools' => 'src/Tools',
        ];

        $aliasConfig = $config['path_aliases'] ?? (($config['paths']['aliases'] ?? null));
        if (!is_array($aliasConfig)) {
            return $aliases;
        }

        foreach ($aliasConfig as $name => $target) {
            if (!is_string($name) || trim($name) === '' || !is_string($target) || trim($target) === '') {
                continue;
            }

            $aliases[$name] = trim($target);
        }

        return $aliases;
    }
}
