<?php

declare(strict_types=1);

namespace Doctrinify\Tools\Tasks\Reporting;

use Doctrinify\Tools\Tasks\Contracts\TaskResultDto;

final class HtmlReportBuilder
{
    /**
     * @param list<TaskResultDto> $results
     */
    public function build(string $reportName, array $results): string
    {
        $riskCounters = ['high' => 0, 'medium' => 0, 'low' => 0, 'unknown' => 0];
        $problemCounters = [];
        $rows = [];

        foreach ($results as $result) {
            $json = $result->json;
            $risk = strtolower((string) ($json['risk_level'] ?? 'unknown'));
            if (!isset($riskCounters[$risk])) {
                $risk = 'unknown';
            }
            $riskCounters[$risk]++;

            $findings = is_array($json['findings'] ?? null) ? $json['findings'] : [];
            foreach ($findings as $finding) {
                if (!is_array($finding)) {
                    continue;
                }
                $type = (string) ($finding['type'] ?? 'unknown');
                if (!isset($problemCounters[$type])) {
                    $problemCounters[$type] = 0;
                }
                $problemCounters[$type]++;
            }

            $model = (string) ($json['model'] ?? $result->taskId);
            $summary = $this->buildSummaryLine($json);
            $rows[] = $this->buildRowHtml($result->taskId, $result->category, $model, $risk, $summary, $json);
        }

        arsort($problemCounters);

        $problemTags = [];
        foreach (array_slice($problemCounters, 0, 7, true) as $problem => $count) {
            $problemTags[] = sprintf('<span class="tag">%s: %d</span>', $this->esc($problem), $count);
        }

        $rowsHtml = implode("\n", $rows);
        $problemHtml = $problemTags !== [] ? implode("\n", $problemTags) : '<span class="tag">No findings yet</span>';

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{$this->esc($reportName)} report</title>
<style>
:root {
  --bg: #f6f7f9;
  --panel: #ffffff;
  --text: #1f2937;
  --muted: #6b7280;
  --border: #d1d5db;
  --high: #b91c1c;
  --medium: #b45309;
  --low: #166534;
}
body { margin:0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif; background: var(--bg); color: var(--text); }
.wrap { max-width: 1200px; margin: 24px auto; padding: 0 16px; }
.panel { background: var(--panel); border: 1px solid var(--border); border-radius: 10px; padding: 16px; margin-bottom: 16px; }
.grid { display:grid; gap:8px; grid-template-columns: repeat(auto-fit,minmax(150px,1fr)); }
.metric { padding: 10px; border:1px solid var(--border); border-radius:8px; }
.metric .v { font-size: 20px; font-weight: 600; }
.filters { display:flex; gap:10px; flex-wrap:wrap; }
input, select { border:1px solid var(--border); border-radius:8px; padding:8px 10px; background:white; }
.card { border:1px solid var(--border); border-radius:8px; margin-bottom:10px; overflow:hidden; }
.card-h { display:flex; justify-content:space-between; gap:8px; padding:10px 12px; background:#f9fafb; }
.badge { padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600; }
.risk-high { background:#fee2e2; color:var(--high); }
.risk-medium { background:#fef3c7; color:var(--medium); }
.risk-low { background:#dcfce7; color:var(--low); }
.risk-unknown { background:#e5e7eb; color:#111827; }
.card-b { padding:12px; }
.tag { display:inline-block; background:#eef2ff; color:#3730a3; border-radius:6px; padding:2px 8px; font-size:12px; margin:2px 6px 2px 0; }
li { margin: 4px 0; }
.small { color: var(--muted); font-size: 12px; }
</style>
</head>
<body>
<div class="wrap">
  <div class="panel">
    <h1 style="margin-top:0">Report: {$this->esc($reportName)}</h1>
    <div class="grid">
      <div class="metric"><div class="small">Models/tasks reviewed</div><div class="v">{$this->esc((string) count($results))}</div></div>
      <div class="metric"><div class="small">High risk</div><div class="v" style="color:var(--high)">{$this->esc((string) $riskCounters['high'])}</div></div>
      <div class="metric"><div class="small">Medium risk</div><div class="v" style="color:var(--medium)">{$this->esc((string) $riskCounters['medium'])}</div></div>
      <div class="metric"><div class="small">Low risk</div><div class="v" style="color:var(--low)">{$this->esc((string) $riskCounters['low'])}</div></div>
    </div>
    <div style="margin-top:10px">{$problemHtml}</div>
  </div>

  <div class="panel">
    <div class="filters">
      <select id="risk"><option value="">All risks</option><option>high</option><option>medium</option><option>low</option><option>unknown</option></select>
      <input id="category" placeholder="Filter by category">
      <input id="model" placeholder="Filter by model name">
    </div>
  </div>

  <div id="cards">
    {$rowsHtml}
  </div>
</div>
<script>
(() => {
  const risk = document.getElementById('risk');
  const category = document.getElementById('category');
  const model = document.getElementById('model');
  const cards = Array.from(document.querySelectorAll('.card'));

  const apply = () => {
    const r = risk.value.toLowerCase();
    const c = category.value.toLowerCase();
    const m = model.value.toLowerCase();

    cards.forEach((card) => {
      const okRisk = !r || card.dataset.risk === r;
      const okCategory = !c || card.dataset.category.includes(c);
      const okModel = !m || card.dataset.model.includes(m);
      card.style.display = (okRisk && okCategory && okModel) ? '' : 'none';
    });
  };

  risk.addEventListener('change', apply);
  category.addEventListener('input', apply);
  model.addEventListener('input', apply);
})();
</script>
</body>
</html>
HTML;
    }

    /**
     * @param array<string, mixed> $json
     */
    private function buildSummaryLine(array $json): string
    {
        if (is_string($json['summary'] ?? null) && $json['summary'] !== '') {
            return $json['summary'];
        }

        $recommendedAction = (string) ($json['recommended_action'] ?? '');
        if ($recommendedAction !== '') {
            return 'Recommended action: ' . $recommendedAction;
        }

        return 'Structured diagnostic result available.';
    }

    /**
     * @param array<string, mixed> $json
     */
    private function buildRowHtml(string $taskId, string $category, string $model, string $risk, string $summary, array $json): string
    {
        $riskClass = in_array($risk, ['high', 'medium', 'low'], true) ? $risk : 'unknown';
        $flags = $this->listToHtml($json['generator_flags'] ?? []);
        $testGaps = $this->listToHtml($json['test_gaps'] ?? []);
        $docUpdates = $this->listToHtml($json['doc_updates'] ?? []);
        $mustVerify = $this->listToHtml($json['must_verify_before_rollout'] ?? []);

        $findingsHtml = '';
        $findings = is_array($json['findings'] ?? null) ? $json['findings'] : [];
        if ($findings !== []) {
            $findingsHtml .= '<ul>';
            foreach ($findings as $finding) {
                if (!is_array($finding)) {
                    continue;
                }
                $findingsHtml .= sprintf(
                    '<li><strong>%s</strong> (%s): %s</li>',
                    $this->esc((string) ($finding['type'] ?? 'unknown')),
                    $this->esc((string) ($finding['severity'] ?? 'info')),
                    $this->esc((string) ($finding['summary'] ?? ''))
                );
            }
            $findingsHtml .= '</ul>';
        }

        $payload = $this->esc((string) json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        return sprintf(
            '<div class="card" data-risk="%s" data-category="%s" data-model="%s">' .
            '<div class="card-h"><div><strong>%s</strong><div class="small">%s | %s</div></div><span class="badge risk-%s">%s</span></div>' .
            '<div class="card-b"><p>%s</p>%s' .
            '<details><summary>Recommended flags</summary>%s</details>' .
            '<details><summary>Test gaps</summary>%s</details>' .
            '<details><summary>Doc updates</summary>%s</details>' .
            '<details><summary>Must verify before rollout</summary>%s</details>' .
            '<details><summary>Raw JSON</summary><pre>%s</pre></details></div></div>',
            $this->esc($risk),
            $this->esc(strtolower($category)),
            $this->esc(strtolower($model)),
            $this->esc($taskId),
            $this->esc($category),
            $this->esc($model),
            $this->esc($riskClass),
            $this->esc(strtoupper($risk)),
            $this->esc($summary),
            $findingsHtml,
            $flags,
            $testGaps,
            $docUpdates,
            $mustVerify,
            $payload,
        );
    }

    /**
     * @param mixed $items
     */
    private function listToHtml(mixed $items): string
    {
        if (!is_array($items) || $items === []) {
            return '<div class="small">none</div>';
        }

        $html = '<ul>';
        foreach ($items as $item) {
            $html .= '<li>' . $this->esc((string) $item) . '</li>';
        }
        $html .= '</ul>';

        return $html;
    }

    private function esc(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
