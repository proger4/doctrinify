# Задача: action-bar.md

## Цель
Реализовать верхнюю панель действий pipeline.

## Входит в задачу
- Элементы: scope name, count badges, global search.
- Кнопки: `Import Scope`, `Attach Metadata`, `Run Introspection`, `Build Candidates`, `Validate`, `Export`.
- Базовая логика enabled/disabled по состояниям scope/pipeline.
- Визуальный статус `Partial export` / `Ready export` на кнопке Export.

## Результат
- Top Action Bar управляет переходами по основному pipeline и отражает статус текущей сессии.
