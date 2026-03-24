# Задача: workspace-registries.md

## Цель
Реализовать центральный registry с режимами Models/Relations.

## Входит в задачу
- Переключение `Models` / `Relations`.
- `Models registry` колонки:
  - readiness
  - display name / class
  - table
  - fields
  - relations
  - custom relations
  - blockers
  - xml status
- `Relations registry` колонки:
  - source
  - target
  - relation name
  - rel type
  - owning side
  - classification
  - errors
  - xml candidate
- Click по строке открывает соответствующий inspector.
- Hover/selection hooks для подсветки связей и интеграции с левым навигатором.

## Не входит (до решений)
- Политика сортировки/фильтрации Models registry.
- Финальный вид joinInfo в Relations registry.

## Результат
- Центр экрана работает как основной реестр моделей и отношений.
