<?php

declare(strict_types=1);

namespace App\Tools\Schemas;

use App\Tools\Config\ConfigLoader;

final class YamlProjectProfile implements ProjectProfileInterface
{
    private const DEFAULT_ROOT_ATTRIBUTES = [
        'xmlns' => 'http://doctrine-project.org/schemas/orm/doctrine-mapping',
    ];

    /**
     * @param array<string, mixed> $config
     */
    public function __construct(private readonly array $config)
    {
    }

    public static function fromFile(string $configPath, ?string $projectRoot = null): self
    {
        $loader = new ConfigLoader();
        $base = $projectRoot ?? (getcwd() ?: '.');
        $resolved = $loader->resolvePath($base, $configPath);
        $resolvedRoot = dirname($resolved);

        return new self($loader->load($resolvedRoot, $resolved));
    }

    /**
     * @return array<string, string>
     */
    public function getDoctrineXmlRootAttributes(): array
    {
        $profile = $this->profileConfig();
        $attributes = $profile['doctrine_xml']['root_attributes'] ?? null;
        if (!is_array($attributes)) {
            return self::DEFAULT_ROOT_ATTRIBUTES;
        }

        $result = [];
        foreach ($attributes as $key => $value) {
            if (!is_string($key) || !is_scalar($value)) {
                continue;
            }
            $result[$key] = (string) $value;
        }

        return $result !== [] ? $result : self::DEFAULT_ROOT_ATTRIBUTES;
    }

    public function getDoctrineXmlFilenamePattern(): string
    {
        $profile = $this->profileConfig();
        $value = $profile['doctrine_xml']['filename_pattern'] ?? '{class}.orm.xml';
        return is_string($value) && $value !== '' ? $value : '{class}.orm.xml';
    }

    public function getGenerationNaming(): string
    {
        $profile = $this->profileConfig();
        $value = $profile['regeneration']['naming'] ?? 'doctrinify';
        return is_string($value) && trim($value) !== '' ? trim($value) : 'doctrinify';
    }

    public function shouldAddGeneratedMarker(): bool
    {
        $profile = $this->profileConfig();
        return (bool) ($profile['regeneration']['add_generated_marker'] ?? true);
    }

    public function shouldEmbedDiagnostics(): bool
    {
        $profile = $this->profileConfig();
        return (bool) ($profile['regeneration']['embed_diagnostics'] ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    private function profileConfig(): array
    {
        if (is_array($this->config['tooling'] ?? null)) {
            return $this->config['tooling'];
        }

        if (is_array($this->config['project_profile'] ?? null)) {
            return $this->config['project_profile'];
        }

        return [];
    }
}
