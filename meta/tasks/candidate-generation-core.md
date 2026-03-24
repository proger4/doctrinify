# Задача: candidate-generation-core.md

## Цель
Реализовать генерацию mapping candidates и базовую классификацию relation.

## Входит в задачу
- `Build Candidates` генерирует:
  - id mapping
  - field mappings
  - association mappings
  - owning side
  - XML preview/status
  - custom relation notes
- Классификация relation: `standard`, `custom`, `blocked`.
- Явная маркировка relation как `custom`, когда она не выражается стандартным Doctrine mapping.

## Не входит (до решений)
- Точные правила присвоения класса `review`.

## Результат
- Пайплайн выдает кандидаты mapping и пригодную для анализа классификацию отношений.
