# Задача: contracts-api-core.md

## Цель
Зафиксировать и реализовать базовые data contracts + session API v1.

## Входит в задачу
- Типы данных:
  - `MigrationScope`
  - `ModelCard`
  - `RelationCard`
  - `Mismatch`
  - `MappingCandidate`
  - `ValidationReport`
  - `ExportBundle`
- Endpoints:
  - `POST /api/migration/session`
  - `POST /api/migration/session/import-scope`
  - `POST /api/migration/session/:id/metadata`
  - `POST /api/migration/session/:id/introspect`
  - `POST /api/migration/session/:id/candidates`
  - `POST /api/migration/session/:id/validate`
  - `POST /api/migration/session/:id/export`
  - `PATCH /api/migration/session/:id/models/:modelId/annotation`
  - `POST /api/migration/session/:id/prompt-extract`

## Не входит (до решений)
- Нужны ли отдельные read API для registry/inspector.
- Финальная backend-стратегия хранения `displayName`/`note`.

## Результат
- Контракты данных и API обеспечивают связанный session-based pipeline от scope до export.
