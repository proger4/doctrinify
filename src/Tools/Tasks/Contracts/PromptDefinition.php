<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Contracts;

final class PromptDefinition
{
    public function __construct(
        public string $name,
        public string $body,
    ) {
    }
}
