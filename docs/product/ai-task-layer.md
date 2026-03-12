# AI Task Layer

## Purpose
AI layer is a supervised diagnostic sidecar for rollout preparation.
It does not generate or rewrite ORM artifacts.

## Main Workbook
- `src/Tools/Tasks/test.xlsx` is the primary task source.

## Input Sources
Task inputs can be built from:
- sandbox models: `sandbox/models`
- sandbox schema: `tests/_data/mock/database/schema.sql`
- generated outputs: `sandbox/doctrine`, `sandbox/models` (in-place generated members)
- mismatch report: `sandbox/doctrine/mismatch-report.txt`

## Required Flow
1. Prepare sandbox models/schema (`tools:sandbox:prepare`).
2. Run core generator (`tools:orm:generate`).
3. Run task execution (`tools:ai:task-execute`).
4. Build HTML report (`tools:report:build`).

## Task Sets
- `models_ai_analysis`: one model per task, risk + flags + test/doc gaps.
- `algorithm_diff`: legacy vs current behavior comparison and regression checklist.
