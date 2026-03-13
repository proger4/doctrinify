<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas;

use Doctrinify\Tools\Config\ConfigLoader;

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

    public static function fromFile(string $projectRoot, string $configPath, ?ConfigLoader $configLoader = null): self
    {
        $loader = $configLoader ?? new ConfigLoader();
        return new self($loader->load($projectRoot, $configPath));
    }

    /**
     * @return array<string, string>
     */
    public function getDoctrineXmlRootAttributes(): array
    {
        $attributes = $this->config['project_profile']['doctrine_xml']['root_attributes'] ?? null;
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
        $value = $this->config['project_profile']['doctrine_xml']['filename_pattern'] ?? '{class}.orm.xml';
        return is_string($value) && $value !== '' ? $value : '{class}.orm.xml';
    }

    public function shouldAddGeneratedMarker(): bool
    {
        return (bool) ($this->config['project_profile']['regeneration']['add_generated_marker'] ?? true);
    }

    public function shouldEmbedDiagnostics(): bool
    {
        return (bool) ($this->config['project_profile']['regeneration']['embed_diagnostics'] ?? false);
    }
}
