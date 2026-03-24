# Задача: export-core.md

## Цель
Реализовать экспортный контур и список export entries.

## Входит в задачу
- Bottom tab `Export` со списком файлов bundle:
  - `mapping_summary.json`
  - `blockers_report.md`
  - `custom_relations_report.md`
  - `validate_checklist.md`
  - entity XML files
- Для каждой записи: `preview`, `copy`, `download`.
- Поддержка `Copy Prompt Extract` в inspector/export контексте.

## Не входит (до решений)
- Точные критерии перехода статусов bundle `partial` и `ready`.

## Результат
- Пользователь видит и выгружает все артефакты миграции поштучно.
