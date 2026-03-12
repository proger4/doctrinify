# Manual CLI Usage

## 1) Register/inspect commands
```bash
php bin/console list | egrep "tools:orm|tools:ai|tools:report"
```

## 2) Prepare clean generator state
```bash
php bin/console tools:sandbox:prepare --clean
php bin/console tools:orm:clean --config=config.yaml
php bin/console tools:orm:generate --config=config.yaml
```

`classlist.txt` behavior:
- if `classlist.txt` exists (or `classlist_path` points to an existing file), generator uses it;
- if missing, generator logs warning and auto-discovers models from `models_path` recursively.

## 3) Verify generator output
```bash
ls -R sandbox
cat sandbox/doctrine/mismatch-report.txt
```

## 4) Run AI diagnostics from main workbook
```bash
php bin/console tools:ai:task-execute --file=src/Tools/Tasks/test.xlsx --task-set=models_ai_analysis
php bin/console tools:ai:task-execute --file=src/Tools/Tasks/test.xlsx --task-set=algorithm_diff
```

## 5) Build reports
```bash
php bin/console tools:report:build --name=models_ai_analysis --open
php bin/console tools:report:build --name=algorithm_diff --open
```

## 6) Point checks
```bash
php bin/console tools:ai:task-execute --file=src/Tools/Tasks/test.xlsx --task-id=MODEL_USER
php bin/console tools:ai:task-execute --file=src/Tools/Tasks/test.xlsx --task-id=DIFF_CORE
```

## Artifacts
- Core: `sandbox/doctrine/*`, `sandbox/models/*.php` (in-place AST), `sandbox/doctrine/mismatch-report.txt`
- AI: `var/tasks/<task_set>/{inputs,prompts,results,reports}`
