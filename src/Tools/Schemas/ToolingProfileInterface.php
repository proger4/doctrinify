<?php

declare(strict_types=1);

namespace App\Tools\Schemas;

interface ToolingProfileInterface
{
    public static function fromFile(string $configPath, ?string $projectRoot = null): self;

    /**
     * @return array<string, string>
     */
    public function getDoctrineXmlRootAttributes(): array;

    public function getDoctrineXmlFilenamePattern(): string;

    public function getGenerationNaming(): string;

    public function shouldAddGeneratedMarker(): bool;

    public function shouldEmbedDiagnostics(): bool;
}
