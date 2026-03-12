# Current State

## Implemented
- CLI commands:
  - `tools:orm:generate`
  - `tools:orm:clean`
  - `tools:ai:task-execute`
  - `tools:report:build`
- Deterministic core generation pipeline is operational.
- `classlist.txt` is optional in runtime flow.
- Missing `classlist.txt` produces warning and falls back to recursive autoscan.
- AI task layer is operational as sidecar diagnostics.
- Workbook default source is `src/Tools/Tasks/test.xlsx`.
- Runtime task artifacts are written to `var/tasks/...`.

## Operational Rule
- First run `tools:orm:generate` on sandbox inputs.
- Then run AI diagnostics and report build.
