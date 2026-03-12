<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Contracts;

final class ReportEntryDto
{
    /**
     * @param list<string> $problemTypes
     * @param list<string> $recommendedFlags
     * @param list<string> $testGaps
     * @param list<string> $docUpdates
     */
    public function __construct(
        public string $taskId,
        public string $category,
        public string $model,
        public string $risk,
        public array $problemTypes,
        public array $recommendedFlags,
        public array $testGaps,
        public array $docUpdates,
        public string $summary,
    ) {
    }
}
