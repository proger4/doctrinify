<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class PersistResultSchema
{
    /**
     * @param list<string> $xmlFiles
     * @param list<string> $phpFiles
     */
    public function __construct(
        public array $xmlFiles,
        public array $phpFiles,
        public string $reportPath,
    ) {
    }
}
