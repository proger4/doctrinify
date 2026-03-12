# Policy Engine Backlog (13 Tasks)

Status legend:
- `done` - implemented
- `planned` - accepted and queued

1. `planned` Central policy engine with strict decisions (`MINIMUM/MAXIMUM/REJECT`).
2. `planned` Unified `GenerationDecision` object as contract between analysis/codegen/persist.
3. `planned` Enforce minimum contract invariants (`table + pk + fields`).
4. `planned` Restrict minimum generation to safe artifact subset only.
5. `planned` Feature gating for maximum mode (inheritance/discriminator/relations/etc).
6. `planned` Hard stage policies for codebase/introspection/analysis/codegen/persist.
7. `planned` Codebase policy: per-model rejection with optional strict-run fail.
8. `planned` Introspection policy: competing hypotheses instead of fake certainty.
9. `planned` Domain exception hierarchy instead of generic runtime failures.
10. `planned` Rich exception payload (`code/model/table/severity/recoverable/fallback`).
11. `planned` Single fallback law: extensions degrade to diagnostics, minimum contract cannot.
12. `planned` Runtime modes: `minimum_or_fail`, `maximum_if_safe`, `strict_all_or_nothing`.
13. `planned` Explicit minimum/maximum comments and diagnostics in report/artifacts.

## Sandbox-first baseline (implemented now)
- `done` Default config switched to `sandbox/*` runtime outputs.
- `done` Added `tools:sandbox:prepare` to sync `tests/_data/mock/models -> sandbox/models`.
- `done` AI sandbox refs prioritize `sandbox/*` artifacts with legacy fallback to `generated/*`.
- `done` Updated docs and manual usage to sandbox-first flow.
