# Задача: inspector-core.md

## Цель
Реализовать правый inspector для модели и relation в 4 режимах.

## Входит в задачу
- Режимы: `summary`, `mapping`, `xml`, `diff`.
- Для модели:
  - summary (fqcn, table, coverage, counts, note)
  - mapping (entity/table/id/fields/associations/custom notes)
  - xml (read-only preview)
  - diff (Yii/SQL/candidate сравнение по ключам)
- Для relation:
  - summary (source/target/name/owning/join/classification/blockers)
  - mapping (association candidate + join/mappedBy/inversedBy notes)
  - xml (association fragment)
  - diff (Yii/SQL/agent/candidate)
- Header copy actions.

## Не входит (до решений)
- Точный UX редактирования displayName/note из inspector.

## Результат
- Inspector даёт полный разбор выбранной модели/связи без смены экрана.
