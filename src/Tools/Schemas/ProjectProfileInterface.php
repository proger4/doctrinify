<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Schemas;

interface ProjectProfileInterface
{
    /**
     * @return array<string, string>
     */
    public function getDoctrineXmlRootAttributes(): array;

    public function getDoctrineXmlFilenamePattern(): string;

    public function shouldAddGeneratedMarker(): bool;

    public function shouldEmbedDiagnostics(): bool;
}
