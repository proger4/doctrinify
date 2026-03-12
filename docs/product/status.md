# Status

- Core pipeline: ready for sandbox generation and repeatable regression checks.
- Default runtime contour is sandbox-first (`sandbox/*`), legacy `generated/*` moved to explicit alternative config.
- AI sidecar: ready for supervised pilot diagnostics over real generator runs.
- Boundary between codegen and diagnostics: explicit and enforced by command separation.
- Unit tests: green on current workspace state.
