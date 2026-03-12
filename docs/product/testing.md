# Testing

## Core Contract Checks
- `OrmGeneratorServiceTest`
- `CommandPipelineTest`
- `ModelIntrospectorTest`
- `PipelineAnalyzerTest`
- `YamlProjectProfileTest`

Run selected:
```bash
vendor/bin/codecept run unit OrmGeneratorServiceTest
vendor/bin/codecept run unit CommandPipelineTest
vendor/bin/codecept run unit ModelIntrospectorTest
vendor/bin/codecept run unit PipelineAnalyzerTest
vendor/bin/codecept run unit YamlProjectProfileTest
```

## Full Unit Run
```bash
vendor/bin/codecept run unit
```

## AI Layer Smoke
```bash
php bin/console tools:ai:task-execute --file=src/Tools/Tasks/test.xlsx --task-set=models_ai_analysis
php bin/console tools:report:build --name=models_ai_analysis --no-open
```
