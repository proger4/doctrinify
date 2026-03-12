<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Execution;

use Doctrinify\Tools\Tasks\Contracts\TaskDefinition;
use Doctrinify\Tools\Tasks\Contracts\TaskResultDto;

final class AiTaskExecutor
{
    /**
     * @param array<string, string> $inputs
     */
    public function execute(TaskDefinition $task, string $taskSet, array $inputs, bool $weakModelMode = true): TaskResultDto
    {
        $json = $taskSet === 'algorithm_diff'
            ? $this->executeAlgorithmDiff($task, $inputs)
            : $this->executeModelAnalysis($task, $inputs, $weakModelMode);

        $json['task_id'] = $task->taskId;
        $json['task_set'] = $taskSet;
        $json['category'] = $task->category;
        $json['created_at'] = gmdate('c');

        return new TaskResultDto(
            taskId: $task->taskId,
            taskSet: $taskSet,
            category: $task->category,
            json: $json,
            markdown: $this->toMarkdown($json),
            createdAt: (string) $json['created_at'],
        );
    }

    /**
     * @param array<string, string> $inputs
     * @return array<string, mixed>
     */
    private function executeModelAnalysis(TaskDefinition $task, array $inputs, bool $weakModelMode): array
    {
        $joined = strtolower(implode("\n", array_values($inputs)));
        $model = $this->detectModelName($inputs, $task->name);
        $table = $this->detectTableName($inputs);

        $findings = [];
        $flags = [];
        $testGaps = ['No regeneration ownership marker asserted'];
        $docUpdates = [];
        $risk = 'low';
        $confidence = $weakModelMode ? 'low' : 'medium';

        $relationSqlPatterns = ['condition', 'join', 'group', 'having', 'order', 'where'];
        foreach ($relationSqlPatterns as $pattern) {
            if (str_contains($joined, $pattern)) {
                $findings[] = [
                    'type' => 'relation_sql_detected',
                    'severity' => 'warning',
                    'summary' => 'Relation contains SQL-specific condition and should be rejected from doctrine generation',
                    'evidence' => [sprintf('keyword detected: %s', $pattern)],
                    'suggested_flag' => 'relations.generate_doctrine=false',
                ];
                $flags[] = 'relations.generate_doctrine=false';
                $docUpdates[] = 'Document why SQL-shaped relation was rejected';
                $risk = 'medium';
                break;
            }
        }

        if (str_contains($joined, 'discriminator') || str_contains($joined, 'inheritance')) {
            $findings[] = [
                'type' => 'inheritance_or_discriminator_risk',
                'severity' => 'warning',
                'summary' => 'Inheritance/discriminator logic may require manual verification before rollout',
                'evidence' => ['keyword detected: discriminator/inheritance'],
                'suggested_flag' => 'single_table_if_one_tree_one_table=true',
            ];
            $flags[] = 'single_table_if_one_tree_one_table=true';
            $risk = 'high';
        }

        if (str_contains($joined, 'primary') && str_contains($joined, 'key') && str_contains($joined, 'composite')) {
            $findings[] = [
                'type' => 'composite_pk_attention',
                'severity' => 'critical',
                'summary' => 'Composite primary key detected, verify generator assumptions and id mapping',
                'evidence' => ['keyword detected: composite primary key'],
                'suggested_flag' => 'primary_key_strategy=database',
            ];
            $flags[] = 'primary_key_strategy=database';
            $risk = 'high';
        }

        if ($findings === []) {
            $findings[] = [
                'type' => 'no_critical_signals_detected',
                'severity' => 'info',
                'summary' => 'No obvious risk markers detected in provided excerpts',
                'evidence' => ['heuristic scan of provided inputs'],
                'suggested_flag' => 'none',
            ];
        }

        if ($risk !== 'low') {
            $testGaps[] = 'No assertion that rejected relations are reflected in mismatch-report';
            $docUpdates[] = 'Add rollout note for risky model and manual checkpoints';
        }

        $flags = array_values(array_unique(array_merge($flags, ['include_mismatch_report=true'])));
        $docUpdates = array_values(array_unique($docUpdates));

        return [
            'model' => $model,
            'table' => $table,
            'risk_level' => $risk,
            'confidence' => $confidence,
            'findings' => $findings,
            'generator_flags' => $flags,
            'test_gaps' => $testGaps,
            'doc_updates' => $docUpdates,
            'recommended_action' => $risk === 'high' ? 'manual_review_before_mass_generation' : 'pilot_ready_with_flags',
        ];
    }

    /**
     * @param array<string, string> $inputs
     * @return array<string, mixed>
     */
    private function executeAlgorithmDiff(TaskDefinition $task, array $inputs): array
    {
        $oldText = '';
        $newText = '';
        foreach ($inputs as $name => $content) {
            $lower = strtolower($name);
            if (str_contains($lower, 'old') || str_contains($lower, 'legacy')) {
                $oldText .= "\n" . $content;
                continue;
            }
            $newText .= "\n" . $content;
        }

        $oldLower = strtolower($oldText);
        $newLower = strtolower($newText);

        $potentialRegressions = [];
        foreach ([
            'discriminator handling' => ['discriminator'],
            'composite PK' => ['composite', 'primary key'],
            'runtime-derived table names' => ['tablename', 'table name', 'runtime'],
        ] as $name => $markers) {
            $oldHas = $this->containsAny($oldLower, $markers);
            $newHas = $this->containsAny($newLower, $markers);
            if ($oldHas && !$newHas) {
                $potentialRegressions[] = [
                    'name' => strtolower(str_replace(' ', '_', $name)),
                    'severity' => 'high',
                    'reason' => sprintf('Legacy excerpts mention "%s" while new excerpts do not.', implode(', ', $markers)),
                ];
            }
        }

        return [
            'scope' => 'algorithm_diff',
            'legacy_strengths' => [
                'Handles dense edge-case logic through runtime checks in single flow',
            ],
            'legacy_risks' => [
                'Low readability and hard-to-isolate behavior in tests',
            ],
            'new_strengths' => [
                'Cleaner pipeline with explicit DTO boundaries',
                'Easier to reason about deterministic output per stage',
            ],
            'new_risks' => [
                'Some implicit legacy behavior may be missing in explicit contracts',
                'Regeneration contract still relies on report semantics',
            ],
            'potential_regressions' => $potentialRegressions,
            'must_verify_before_rollout' => [
                'discriminator handling',
                'composite PK',
                'runtime-derived table names',
            ],
            'recommended_rollout_strategy' => [
                'pilot_on_selected_models',
                'enable_flags_incrementally',
                'compare_xml_outputs_for_hotspot_models',
            ],
            'summary' => sprintf('Task %s compared legacy and new excerpts.', $task->taskId),
        ];
    }

    /**
     * @param array<string, string> $json
     */
    private function toMarkdown(array $json): string
    {
        return "```json\n" . ((string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) . "\n```\n";
    }

    /**
     * @param array<string, string> $inputs
     */
    private function detectModelName(array $inputs, string $fallback): string
    {
        foreach ($inputs as $content) {
            if (preg_match('/class\\s+([A-Za-z0-9_]+)\\s+extends/', $content, $m) === 1) {
                return $m[1];
            }

            if (preg_match('/namespace\\s+([A-Za-z0-9_\\\\]+)\s*;/', $content, $ns) === 1
                && preg_match('/class\\s+([A-Za-z0-9_]+)/', $content, $cl) === 1) {
                return $ns[1] . '\\\\' . $cl[1];
            }
        }

        return $fallback;
    }

    /**
     * @param array<string, string> $inputs
     */
    private function detectTableName(array $inputs): string
    {
        foreach ($inputs as $content) {
            if (preg_match('/create\\s+table\\s+[`\"]?([A-Za-z0-9_]+)[`\"]?/i', $content, $m) === 1) {
                return strtoupper($m[1]);
            }
            if (preg_match('/table\\s*[:=]\\s*([A-Za-z0-9_]+)/i', $content, $m) === 1) {
                return strtoupper($m[1]);
            }
        }

        return 'UNKNOWN';
    }

    /**
     * @param list<string> $needles
     */
    private function containsAny(string $text, array $needles): bool
    {
        foreach ($needles as $needle) {
            if (str_contains($text, strtolower($needle))) {
                return true;
            }
        }

        return false;
    }
}
