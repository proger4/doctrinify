<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Catalog;

final class ModelsAiAnalysisTaskFactory
{
    public function outputSchemaJson(): string
    {
        return <<<'JSON'
{
  "model": "app\\models\\User",
  "table": "USER",
  "risk_level": "low|medium|high",
  "confidence": "low|medium|high",
  "findings": [
    {
      "type": "relation_sql_detected",
      "severity": "info|warning|critical",
      "summary": "...",
      "evidence": ["..."],
      "suggested_flag": "..."
    }
  ],
  "generator_flags": ["..."],
  "test_gaps": ["..."],
  "doc_updates": ["..."],
  "recommended_action": "manual_review_before_mass_generation|pilot_ready_with_flags"
}
JSON;
    }

    /**
     * @return list<string>
     */
    public function checklist(): array
    {
        return [
            'Focus on one model only.',
            'Flag SQL-specific relation conditions.',
            'Check tableName / primaryKey / discriminator stability.',
            'Return strict JSON only as final answer.',
        ];
    }
}
