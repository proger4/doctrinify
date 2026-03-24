## Вердикт {c8d91e2}

### 1) Что реально есть сейчас (по коду)

- Runtime-команды: `tools:orm:clean`, `tools:orm:generate`, `tools:sandbox:prepare` ([bin/console](/Users/vanopersimmon/Documents/Projects/doctrinify/bin/console:13)).
- Генераторный pipeline детерминированный: load config/codebase -> AST model introspection -> DB introspection -> analysis -> generation -> persist mismatch report ([OrmGeneratorService.php](/Users/vanopersimmon/Documents/Projects/doctrinify/src/Service/OrmGeneratorService.php:73)).
- Модельная интроспекция AST-only: `tableName`, `primaryKey`, `rules`, `attributeLabels`, `get*` relations, SQL modifiers, частичный discriminator из `find()->where(['type'=>...])` и `beforeSave` ([AstFacade.php](/Users/vanopersimmon/Documents/Projects/doctrinify/src/Tools/AST/AstFacade.php:123), [AstFacade.php](/Users/vanopersimmon/Documents/Projects/doctrinify/src/Tools/AST/AstFacade.php:346), [AstFacade.php](/Users/vanopersimmon/Documents/Projects/doctrinify/src/Tools/AST/AstFacade.php:401)).
- `classlist` опционален: если файла нет, loader предупреждает и делает autoscan + blacklist/base-class filtering ([CodebaseInputLoader.php](/Users/vanopersimmon/Documents/Projects/doctrinify/src/Tools/Codebase/CodebaseInputLoader.php:73)).
- Analyzer формирует диагностические факты (не автопочинку), mismatch-report пишется в `.../mismatch-report.txt` ([PipelineAnalyzer.php](/Users/vanopersimmon/Documents/Projects/doctrinify/src/Tools/Analysis/PipelineAnalyzer.php:74), [ArtifactPersister.php](/Users/vanopersimmon/Documents/Projects/doctrinify/src/Tools/Persist/ArtifactPersister.php:38)).
- UI — аналитический mock-shell на статических массивах, но с правильной структурой `models/relations/issues/inspector/workbench` ([App.tsx](/Users/vanopersimmon/Documents/Projects/doctrinify/ui/src/app/App.tsx:151), [App.tsx](/Users/vanopersimmon/Documents/Projects/doctrinify/ui/src/app/App.tsx:266), [App.tsx](/Users/vanopersimmon/Documents/Projects/doctrinify/ui/src/app/App.tsx:1613)).
- В текущем runtime отсутствуют `tools:ai:*` и `tools:report:*`, значит `roo` и `qwen-coder-ai` нужно подключать sidecar-процессом поверх результатов `tools:orm:generate`, не внутрь генератора.

### 2) Как ужать scope до закрытия анализа за 3 часа

#### UI

- Оставить только 4 рабочих зоны:
  - `Models Registry` (ok/review/blocked),
  - `Issues/Blockers`,
  - `Inspector Evidence`,
  - `UML/Graph preview`.
- Заморозить все вторичное (editor-like UX, лишние меню/истории).
- Добавить 3 P0-фильтра:
  - `edge_only`,
  - `blocked_only`,
  - `manual_decision_required`.

#### Introspection

- Не расширять AST парсер в этом цикле.
- Добавить только post-step usage scan (`rg` по FQCN/table/relation/critical columns).
- Выход по каждой модели строго в минимальной схеме:
  - `status: ok|review|blocked`,
  - `mismatch_codes[]`,
  - `evidence[]`,
  - `recommended_action`.

#### Contracts

- Ограничить triage-коды до ядра:
  - `MODEL_TABLE_MISMATCH`
  - `FIELD_COLUMN_MISMATCH`
  - `RELATION_FK_MISMATCH`
  - `RELATION_TYPE_MISMATCH`
  - `RELATION_COMPLEX_SQL`
  - `OWNING_SIDE_UNKNOWN`
  - `TYPE_MISMATCH`
  - `NULLABILITY_MISMATCH`
  - `DISCRIMINATOR_UNCLEAR`
  - `DISCRIMINATOR_SOURCE_CONFLICT`
  - `CYCLIC_RELATION_CASE`
  - `MISSING_PK_OR_FK_CASE`

#### AI agent systems

- `roo`: оркестрирует батчи 39 моделей, следит за полнотой 39/39 и сборкой финальных артефактов.
- `qwen-coder-ai`: классифицирует mismatch code по evidence и выдает короткое объяснение + действие.
- Жесткая граница: AI не меняет codegen/runtime artifacts автоматически; AI только диагностирует.

### 3) Как формулировать несоответствие (без вкусовщины)

Каждый mismatch фиксировать как запись:

- `subject`
- `claim`
- `sql_fact`
- `yii_fact`
- `usage_fact`
- `impact`
- `decision: auto|manual|defer`

Правило: если нет минимум двух независимых источников фактов (`sql+yii` или `sql+usage`) — решение только `manual`.

### 4) Пограничные случаи (что это у нас)

- `RELATION_COMPLEX_SQL`: relation с `where/onCondition/joinWith/orderBy/viaTable`.
- `RELATION_FK_MISMATCH`: relation declared, но FK не подтверждает mapping.
- `OWNING_SIDE_UNKNOWN`: bidirectional кейс без однозначного owning side.
- `DISCRIMINATOR_UNCLEAR`: поле `type` есть, но роль (STI или бизнес-флаг) неочевидна.
- `DISCRIMINATOR_SOURCE_CONFLICT`: `find()/beforeSave()/afterFind()/beforeValidate` дают конфликтующую семантику.
- `CYCLIC_RELATION_CASE`: цикл связей, который может ломать безопасную генерацию/валидацию.
- `MISSING_PK_OR_FK_CASE`: нельзя надежно вывести mapping из-за отсутствующих ключей.

### 5) Дискриминаторы: чтобы не зафакапиться

Проверять минимум 4 сигнала:

- `find()->where(['type' => ...])`
- `beforeSave` assignment `type`
- `afterFind` логика по `type`
- `beforeValidate`/behavior hooks, влияющие на `type`

Если сигналы конфликтуют:

- ставить `DISCRIMINATOR_SOURCE_CONFLICT`,
- блокировать auto-rollout,
- отправлять кейс в manual decision.

### 6) Таймбокс выполнения

- `0:00-1:00` — заливка и запуск в закрытом контуре, smoke прогоны генератора.
- `1:00-1:10` — подключение интроспекции к 39 моделям + usage scan.
- `1:10-1:15` — дотюнинг roo skill и qwen output-schema.
- `1:15-3:00` — triage, ручная валидация edge cases, финализация артефактов.

### 7) Что обязательно сдать в конце

- `ok_tables` — таблицы, где готово для Doctrine (ключи/поля/связи/готовность к XML).
- `not_ok_tables` — таблицы с mismatch codes, evidence, impact и decision.
- `uml_with_problem_highlight` — диаграмма с цветовой подсветкой проблемных связей и списком проблем на таблицу.
- `proposed_decisions` — приоритетный набор решений перед rollout.

### 8) Антифейл-чеклист

- Не расширять scope после старта triage.
- Не смешивать codegen и diagnostics контур.
- Не принимать mismatch без evidence.
- Все спорные кейсы — `manual`, без угадываний.
- Definition of done: покрытие 39/39 + 4 итоговых артефакта.
