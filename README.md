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
