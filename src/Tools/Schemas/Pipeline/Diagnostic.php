<?php

declare(strict_types=1);

namespace App\Tools\Schemas\Pipeline;

final readonly class Diagnostic
{
    /**
     * @param array<string, string> $context
     */
    public function __construct(
        public string $severity,
        public string $message,
        public array $context,
    ) {
    }
}
