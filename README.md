# Doctrinify

Минимальный pipeline для генерации Doctrine XML и AST-аксессоров поверх Yii1 ActiveRecord.

## Что делает
- читает модели из `models_path` (`classlist.txt` опционален; при отсутствии — autoscan);
- строит иерархии наследования моделей (через `nicmart/tree`);
- интроспектит модели и SQL-схему;
- принимает решения в analysis-слое (включая отбрасывание relations с SQL-модификаторами);
- генерирует Doctrine XML и AST-мутации в существующих PHP-моделях;
- пишет mismatch-report.

## Слои
`Codebase -> Introspection -> Analysis -> Schemas -> Codegen -> Persist`

## Команды
- `php bin/console tools:sandbox:prepare --clean`
- `php bin/console tools:orm:clean --config=config.yaml`
- `php bin/console tools:orm:generate --config=config.yaml`

## Контракт конфига (`config.yaml`)
- `models_path`
- `doctrine_xml_path`
- `schema_path`
- `base_classes`
- `blacklist`
- `model_scan_exclude_dirs`
- `classlist_path` (опционально)
- `flags.generate_doctrine_xml`
- `flags.generate_php_accessors`
- `tooling.doctrine_xml.root_attributes`
- `tooling.doctrine_xml.filename_pattern`
- `tooling.regeneration.naming`
- `tooling.regeneration.add_generated_marker`
- `tooling.regeneration.embed_diagnostics`

## Важные правила
- PHP-генерация работает только как AST-патчинг существующих файлов.
- Абстрактные классы участвуют в анализе иерархий, но XML для них не создаётся.
- Сгенерированные AST-члены совместимы с PHP 7.3 (без typed properties и `mixed`-type hints).
- Sidecar AI/tasks удалён из runtime; гипотезы и проверки ведутся вручную по mismatch-report и XML.

## FIXES:

Ниже минимальные сниппеты для хардкода MVP.

`/Users/vanopersimmon/Documents/Projects/doctrinify/src/Service/OrmGeneratorService.php`

```php
// сразу после $selectedBatches = ...
$batchesByClass = [];
foreach ($batches as $candidateBatch) {
    $batchesByClass[$candidateBatch->className] = $candidateBatch;
}
$xmlGeneratedFor = [];
```

```php
// внутри foreach ($selectedBatches as $batch) { ... } заменить XML-блок на это:
$xml = null;
$xmlFilename = null;
if ($codebase->config->generateXml) {
    $xmlBatch = $this->resolveXmlBatch($batch, $batchesByClass, $codebase->classFiles);
    if ($xmlBatch !== null) {
        $xmlClass = $xmlBatch->className;
        $xmlKey = strtolower($xmlClass);

        if (!isset($xmlGeneratedFor[$xmlKey])) {
            $xmlGeneratedFor[$xmlKey] = true;

            $xmlDiagnostics = $this->diagnosticsForClass($analysis->diagnostics, $xmlClass);
            $xml = $this->xmlCodeGenerator->generate(
                className: $xmlClass,
                analyzedModel: $xmlBatch->analyzedModel,
                table: $xmlBatch->table,
                stiByRoot: $analysis->stiByRoot,
                modelMetas: $models,
                profile: $profile,
                diagnostics: $xmlDiagnostics,
            );
            $xmlFilename = $this->xmlCodeGenerator->buildFilename($xmlClass, $profile);
        }
    }
}
```

```php
// добавить в класс новый helper:
private function resolveXmlBatch(
    \App\Tools\Schemas\Pipeline\AnalysisBatchSchema $batch,
    array $batchesByClass,
    array $classFiles
): ?\App\Tools\Schemas\Pipeline\AnalysisBatchSchema {
    $current = $batchesByClass[$batch->className] ?? null;
    if ($current === null) {
        return null;
    }

    $currentIsBase = $this->isBaseClass($current->className, $classFiles[$current->className] ?? null);
    $currentValid = !$current->analyzedModel->model->isAbstract
        && $current->table !== null
        && $current->analyzedModel->resolvedTable !== null;

    if (!$currentIsBase && $currentValid) {
        return $current;
    }

    $resolvedTable = strtolower((string) $current->analyzedModel->resolvedTable);

    foreach ($current->hierarchyClasses as $candidateClass) {
        $candidate = $batchesByClass[$candidateClass] ?? null;
        if ($candidate === null) {
            continue;
        }

        if ($this->isBaseClass($candidate->className, $classFiles[$candidate->className] ?? null)) {
            continue;
        }

        if ($candidate->analyzedModel->model->isAbstract || $candidate->table === null || $candidate->analyzedModel->resolvedTable === null) {
            continue;
        }

        if ($resolvedTable !== '' && strtolower((string) $candidate->analyzedModel->resolvedTable) !== $resolvedTable) {
            continue;
        }

        return $candidate;
    }

    return $currentValid ? $current : null;
}

private function isBaseClass(string $className, ?string $filePath): bool
{
    if (stripos($className, '\\_base\\') !== false) {
        return true;
    }

    if (is_string($filePath) && $filePath !== '') {
        $normalized = strtolower(str_replace('\\', '/', $filePath));
        return preg_match('~(?:^|/)_base(?:/|$)~', $normalized) === 1;
    }

    return false;
}
```

Это даст ровно то, что ты хочешь:
- XML на конечный `resolved` класс (и его PK),
- PHP-свойства остаются в `_base` через уже существующий `resolvePhpMutationTargetClass()`.