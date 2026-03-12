# Architecture

## Core Pipeline
1. `Codebase` (`src/Tools/Codebase`) loads config and resolves model classes.
: `classlist.txt` is optional override; if missing, loader performs recursive autoscan of `models_path`.
2. `Introspection` (`src/Tools/Introspection`) reads model + schema metadata.
3. `Analysis` (`src/Tools/Analysis`) resolves mapping rules and diagnostics.
4. `Codegen` (`src/Tools/Codegen`) builds Doctrine XML and PHP accessors.
5. `Persist` (`src/Tools/Persist`) writes artifacts and mismatch-report.
6. Orchestration in `src/Service/OrmGeneratorService.php`.

## AI Sidecar Layer
- Located in `src/Tools/Tasks`.
- Reads workbook `src/Tools/Tasks/test.xlsx`.
- Builds inputs from sandbox refs (`tests/_data/mock/*`) and real generator outputs (`generated/*`).
- Stores runtime artifacts under `var/tasks/<task_set>/{inputs,prompts,results,reports}`.
- Never mutates generator output directly.

## Boundary Contract
- `OrmGeneratorService` is unaware of AI tasks.
- `Tools/Tasks` is unaware of ORM generation internals beyond reading produced files.
