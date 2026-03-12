# Shared Context (Multi-Agent)

Last updated: 2026-03-13 (Europe/Moscow)

## Product Snapshot
- `doctrinify` converts legacy Yii-style models into Doctrine XML and generated PHP accessors.
- Core pipeline is deterministic: config -> model introspection -> schema introspection -> analysis -> generation -> persist.
- AI layer is sidecar diagnostics only. It does not change codegen logic or generated artifacts.

## Operational Boundaries
- `tools:orm:*` handles generation and cleanup only.
- `tools:ai:*` handles diagnostic tasks only.
- `tools:report:*` handles HTML visualization only.

## Sandbox-First AI Scenario
1. Sync fixtures into runtime sandbox: `php bin/console tools:sandbox:prepare --clean` (`tests/_data/mock/models` -> `sandbox/models`).
2. Run regular generator pipeline (`tools:orm:generate`).
3. Run AI tasks from `src/Tools/Tasks/test.xlsx`.
4. Build reports from `var/tasks/<task_set>/reports/*.html`.

## Critical Rule
- AI analyzes a real generator run (models + schema + generated artifacts + mismatch-report), not project text in isolation.
