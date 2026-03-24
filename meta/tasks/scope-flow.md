# Задача: scope-flow.md

## Цель
Закрыть рабочий поток создания scope до интроспекции.

## Входит в задачу
- `Import Scope Dialog`:
  - ввод списка FQCN
  - импорт `scope.json`
  - preview normalized models до подтверждения
- Создание scope и наполнение `Left Navigator` моделями со статусом `new`.
- Возможность удаления отдельных моделей из scope до запуска интроспекции.
- Контракт статусов `migration_scope`: `draft|loaded|introspected|candidate_built|validated|has_blockers`.

## Результат
- Пользователь может сформировать и скорректировать scope перед запуском `Run Introspection`.
