# Задача: blockers-validate.md

## Цель
Закрыть рабочий контур проблем и проверки готовности.

## Входит в задачу
- Bottom tab `Blockers`:
  - таблица с колонками `severity`, `code`, `subject`, `message`, `source`, `fixability`
  - действия `Copy item` и `Copy JSON`
- Словарь mismatch codes и severity (`warning`/`blocking`).
- Bottom tab `Validate`:
  - checklist items из ТЗ
  - summary counts
  - статус `pass/fail`
  - отображение причины fail при blocking issues

## Не входит (до решений)
- Окончательная формула `Validate=pass` (строго по отсутствию blocking или по полному checklist-pass).

## Результат
- Прозрачная диагностика blockers и понятный статус готовности перед экспортом.
