<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Catalog;

final class AlgorithmDiffTaskFactory
{
    public function outputSchemaJson(): string
    {
        return <<<'JSON'
{
  "scope": "algorithm_diff",
  "legacy_strengths": ["..."],
  "legacy_risks": ["..."],
  "new_strengths": ["..."],
  "new_risks": ["..."],
  "potential_regressions": [
    {
      "name": "...",
      "severity": "low|medium|high",
      "reason": "..."
    }
  ],
  "must_verify_before_rollout": ["..."],
  "recommended_rollout_strategy": ["..."]
}
JSON;
    }

    /**
     * @return list<string>
     */
    public function checklist(): array
    {
        return [
            'Compare old and new behavior, not style.',
            'Highlight potential behavior regressions.',
            'Return compact and structured output only.',
        ];
    }
}
