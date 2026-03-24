# UI to Meta Bidings Logic

## 1) Базовая проекция данных
- UI работает поверх `objects`, `object_facts`, `links`, `issues`.
- `object_key` форматы:
  - `entity:<FQCN>`
  - `table:<TABLE_NAME>`
  - `column:<TABLE_NAME>.<COLUMN_NAME>`
  - `relation:<FQCN>::<relationName>`
  - `inheritance:<ROOT_FQCN>`
  - `discriminator:<FQCN>:<column>`
- `fact_kind`:
  - `observed` — attach metadata/introspection raw facts
  - `derived` — merge/normalization facts
  - `decision` — принятые решения
  - `target` — целевое mapping состояние
  - `result` — post-validate/post-export состояние

## 2) Pipeline actions -> contracts

| UI action | Writes in meta | Main contract codes |
|---|---|---|
| `Import Scope` | `objects` (entity), `object_facts` (`observed`) | `ENT.AR_MODEL` |
| `Attach Metadata` | `objects` (table/column/relation/inheritance/discriminator), `object_facts` (`observed`) | `TBL.PK`, `TBL.ID_STYLE`, `COL.TYPE`, `COL.NULLABLE`, `COL.UNIQUE`, `COL.FK_TARGET`, `ENT.TABLE`, `ENT.BASE_CLASS`, `ENT.ABSTRACT`, `REL.DECLARED`, `REL.KIND`, `REL.TARGET`, `REL.JOIN`, `INH.*`, `DISC.*` |
| `Run Introspection` | `object_facts` (`derived`), `links` (`observed`), `issues` (`open`) | `REL.USAGE_CARDINALITY`, `REL.SQL_COMPLEXITY`, `CHK.*` |
| `Build Candidates` | `object_facts` (`decision` + `target`), `links` (`target`) | `REL.DOCTRINE_CANDIDATE`, `DEC.STATUS`, `DEC.REPOSITORY_QUERY`, `DEC.MANUAL_MAPPING`, `DEC.READ_ONLY` |
| `Validate` | `object_facts` (`result`), `issues` (status update) | `FIN.MAPPING_VALID`, `CHK.NULL_MISMATCH`, `CHK.TYPE_MISMATCH`, `CHK.REL_COMPLEX`, `CHK.INHERITANCE_AMBIGUOUS` |
| `Export` | `object_facts` (`result`), `issues` close/keep, export artifacts | `FIN.MAPPING_VALID` (+ требуется расширение контрактов под artifact entries) |

## 3) UI components -> reads/writes

### 3.1 Top Action Bar (`Import Scope`, `Attach Metadata`, `Run Introspection`, `Build Candidates`, `Validate`, `Export`)
- Reads:
  - counts по `objects` (`entity`, `relation`)
  - blockers из `issues where status=open and severity in (high, blocker)`
  - readiness из `object_facts` (`DEC.STATUS`, `FIN.MAPPING_VALID`)
- Writes:
  - запускает pipeline шаги (см. раздел 2)

### 3.2 Left Navigator / Models Explorer
- Reads:
  - `objects where object_type=entity`
  - counters через `issues` и `links`
  - relation highlight через `links` + blocking issues
  - readiness через `DEC.STATUS`/`FIN.MAPPING_VALID`
- Writes:
  - выбор модели -> UI state selection
  - `Rename`/`Edit note` -> `object_facts(decision)` для аннотаций модели (требуется отдельный `contract_code` для annotation)
  - `Remove from scope` -> status update у `entity` объекта

### 3.3 Scope Filter
- Reads:
  - scope filters (entity set/layers)
- Writes:
  - scope selection criteria (требуется `object_type=scope` или отдельный scope контракт)

### 3.4 Relations Focus / Relation Highlight
- Reads:
  - `links` по текущей выбранной модели
  - `issues` по relation subject
- Writes:
  - нет прямых write в meta
- Mapping:
  - `outgoing/incoming/both` <- направленные `links`
  - `custom` <- `DEC.REPOSITORY_QUERY`/`DEC.MANUAL_MAPPING`
  - `blocking` <- open blocking issues

### 3.5 Issue Radar + Workbench/Blockers
- Reads:
  - `issues` (`severity`, `code`, `subject`, `summary`, `source`, `execution_mode`)
- Writes:
  - issue status transitions `open -> accepted/resolved/wont_fix`
  - decision notes в `issues.decision_note`

### 3.6 Center Workspace / Models Registry
- Reads:
  - entity rows: `objects(entity)` + агрегаты facts/issues/links
  - xml status: из `target/result` facts (`FIN.MAPPING_VALID` + candidate status)
- Writes:
  - row selection -> UI state

### 3.7 Center Workspace / Relations Registry
- Reads:
  - `objects(relation)`
  - `REL.KIND`, `REL.TARGET`, `REL.JOIN`, `REL.DOCTRINE_CANDIDATE`
  - owning side из relation decision facts (требуется отдельный код, напр. `REL.OWNING_SIDE`)
  - classification из decision facts + issues
- Writes:
  - relation selection -> UI state

### 3.8 Right Inspector (`summary/mapping/xml/diff`)
- Summary reads:
  - `ENT.*`, `REL.*`, `INH.*`, `DISC.*`, issue counters
- Mapping reads:
  - `target` facts (`REL.DOCTRINE_CANDIDATE`, `DEC.*`)
- XML reads:
  - target/export artifact content (требуется контракт artifact)
- Diff reads:
  - observed vs target vs result факты по одинаковым `contract_code`
- Writes:
  - copy/download actions не меняют meta
  - manual approve/reject решений -> `object_facts(decision)` / `issues` status

### 3.9 Bottom Workbench / Validate
- Reads:
  - checklist из `CHK.*` и `FIN.MAPPING_VALID`
  - blockers из `issues`
- Writes:
  - validate run -> `result` facts

### 3.10 Bottom Workbench / Export
- Reads:
  - export entries и readiness
- Writes:
  - export result markers в `object_facts(result)`
  - artifact registry (требуется расширение сверх текущего `contracts_draft.md`)

## 4) UI search -> meta query binding
- Search `model` -> `objects(entity).canonical_name` + alias/display facts
- Search `table` -> `objects(table).canonical_name`
- Search `issue` -> `issues.issue_code`, `issues.summary`, `issues.subject_ref`
- Search `term/component` -> UI-only glossary, без meta writes

## 5) Required meta extensions for full UI compatibility
- Добавить контракты для:
  - model annotation (`displayName`, `note`)
  - relation owning/inverse side явным кодом
  - export artifact entries (`mapping_summary.json`, `blockers_report.md`, `custom_relations_report.md`, `validate_checklist.md`, `*.orm.xml`)
  - scope object и scope state transitions
- Добавить `run`/`artifact` сущности для стабильного цикла `introspection -> candidate -> validate -> export -> re-introspection`.

## 6) Legacy migration loop binding (Rector + AI)
- Input to loop:
  - `issues where status=open`
  - `object_facts where contract_code like 'DEC.%'`
- Output from loop:
  - patched code artifacts
  - updated `result` facts
  - issue status updates
- Next iteration trigger:
  - `run_introspection` with new `run_id`
  - diff against previous `run_id` by (`object_key`, `contract_code`, `fact_kind`)
