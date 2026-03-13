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
        if ($path === '') {
            return $projectRoot;
        }

        if ($path[0] === '/') {
            return $path;
        }

        return rtrim($projectRoot, '/') . '/' . ltrim($path, './');
    }
}
