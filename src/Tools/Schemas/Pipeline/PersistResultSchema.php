<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class PersistResultSchema
{
    /**
     * @param array<string> $xmlFiles
     * @param array<string> $phpFiles
     */
    public function __construct(
        public array $xmlFiles,
        public array $phpFiles,
        public string $reportPath,
    ) {
    }
}
