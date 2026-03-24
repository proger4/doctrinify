# Задача: model-explorer-core.md

## Цель
Собрать ядро левого Model Explorer как главный навигатор по моделям.

## Входит в задачу
- Строка модели с полями: `displayName`, `fqcn`, `table`, `readiness`, `blockerCount`, `customRelationCount`.
- `View preset switcher`: `Explorer`, `Tables`, `FQCN`.
- Поиск по FQCN/table/alias/note.
- Inline actions: `Rename`, `Edit note`, `Copy FQCN`, `Remove from scope`.
- Редактирование `displayName` и `note` (popover/inline по текущему UI-решению).

## Не входит (до решений)
- Отдельные фильтры readiness/blockers/custom в левом explorer.

## Результат
- Левый навигатор покрывает основную навигацию по scope и аннотацию моделей.
