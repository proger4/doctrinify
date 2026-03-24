# Enhanced Contracts List

## 1) SQLite базовые требования
- `PRAGMA foreign_keys = ON`
- `PRAGMA journal_mode = WAL`
- `PRAGMA synchronous = NORMAL`
- `PRAGMA temp_store = MEMORY`
- `run_id` обязателен для всех записей `object_facts`, `links`, `issues`

## 2) Таблицы

### `objects`
- `object_key TEXT PRIMARY KEY`
- `object_type TEXT NOT NULL`
- `canonical_name TEXT NOT NULL`
- `layer TEXT NOT NULL`
- `root_object_key TEXT NULL REFERENCES objects(object_key)`
- `parent_object_key TEXT NULL REFERENCES objects(object_key)`
- `status TEXT NOT NULL`
- `created_at TEXT NOT NULL`
- `updated_at TEXT NOT NULL`

### `object_facts`
- `fact_id INTEGER PRIMARY KEY`
- `object_key TEXT NOT NULL REFERENCES objects(object_key)`
- `contract_code TEXT NOT NULL`
- `fact_kind TEXT NOT NULL`
- `value_json TEXT NOT NULL`
- `source_kind TEXT NOT NULL`
- `source_ref TEXT NULL`
- `source_ref_json TEXT NULL`
- `status TEXT NOT NULL`
- `confidence TEXT NOT NULL`
- `run_id TEXT NOT NULL`
- `evidence_hash TEXT NULL`
- `supersedes_fact_id INTEGER NULL REFERENCES object_facts(fact_id)`
- `created_at TEXT NOT NULL`

### `links`
- `link_id INTEGER PRIMARY KEY`
- `from_object_key TEXT NOT NULL REFERENCES objects(object_key)`
- `link_type TEXT NOT NULL`
- `to_object_key TEXT NOT NULL REFERENCES objects(object_key)`
- `link_kind TEXT NOT NULL`
- `status TEXT NOT NULL`
- `payload_json TEXT NULL`
- `confidence TEXT NOT NULL`
- `run_id TEXT NOT NULL`
- `created_at TEXT NOT NULL`

### `issues`
- `issue_id INTEGER PRIMARY KEY`
- `object_key TEXT NOT NULL REFERENCES objects(object_key)`
- `subject_type TEXT NOT NULL`
- `subject_ref TEXT NOT NULL`
- `issue_code TEXT NOT NULL`
- `severity TEXT NOT NULL`
- `status TEXT NOT NULL`
- `summary TEXT NOT NULL`
- `execution_mode TEXT NOT NULL`
- `decision_note TEXT NULL`
- `detected_run_id TEXT NOT NULL`
- `resolved_run_id TEXT NULL`
- `resolution_fact_id INTEGER NULL REFERENCES object_facts(fact_id)`
- `created_at TEXT NOT NULL`
- `updated_at TEXT NOT NULL`

### `runs`
- `run_id TEXT PRIMARY KEY`
- `scope_id TEXT NOT NULL`
- `stage TEXT NOT NULL`
- `started_at TEXT NOT NULL`
- `finished_at TEXT NULL`
- `status TEXT NOT NULL`
- `initiator_kind TEXT NOT NULL`
- `initiator_ref TEXT NULL`

### `artifacts`
- `artifact_id INTEGER PRIMARY KEY`
- `run_id TEXT NOT NULL REFERENCES runs(run_id)`
- `artifact_kind TEXT NOT NULL`
- `object_key TEXT NULL REFERENCES objects(object_key)`
- `name TEXT NOT NULL`
- `mime_type TEXT NOT NULL`
- `content_hash TEXT NOT NULL`
- `content_ref TEXT NULL`
- `created_at TEXT NOT NULL`

## 3) Индексы и уникальности
- `UNIQUE(objects.object_type, objects.canonical_name, objects.layer)`
- `INDEX(object_facts.object_key, object_facts.contract_code, object_facts.fact_kind)`
- `UNIQUE(object_facts.object_key, object_facts.contract_code, object_facts.fact_kind, object_facts.run_id, object_facts.source_kind, object_facts.evidence_hash)`
- `INDEX(links.from_object_key, links.link_type, links.to_object_key)`
- `INDEX(issues.object_key, issues.issue_code, issues.status)`
- `INDEX(issues.subject_type, issues.subject_ref)`
- `INDEX(runs.scope_id, runs.stage, runs.status)`
- `INDEX(artifacts.run_id, artifacts.artifact_kind)`

## 4) `object_type`
- `scope`
- `table`
- `column`
- `entity`
- `relation`
- `inheritance`
- `discriminator`
- `aggregate`
- `metamodel`
- `artifact`

## 5) `layer`
- `gx_base`
- `base`
- `model`
- `metamodel`
- `external`

## 6) `fact_kind`
- `observed`
- `derived`
- `decision`
- `target`
- `result`

## 7) `source_kind`
- `sql`
- `yii`
- `usage`
- `manual`
- `llm`
- `rector`
- `doctrine_validate`
- `runtime_trace`

## 8) `link_type`
- `entity_table`
- `relation_owner`
- `relation_target`
- `relation_local_column`
- `relation_remote_column`
- `inheritance_root`
- `inheritance_child`
- `discriminator_column`
- `model_baseclass`
- `model_metamodel`
- `aggregate_root_member`
- `issue_subject`
- `artifact_object`

## 9) Контрактные коды: DB
- `TBL.PK`
- `TBL.ID_STYLE`
- `TBL.UNIQUE_SET`
- `TBL.INDEX_SET`
- `TBL.CHECK_SET`
- `DB.SEQUENCE`
- `DB.TRIGGER`
- `DB.DEFAULT`

## 10) Контрактные коды: Column
- `COL.TYPE`
- `COL.NULLABLE`
- `COL.UNIQUE`
- `COL.FK_TARGET`
- `COL.PRECISION`
- `COL.SCALE`
- `COL.LENGTH`
- `COL.DEFAULT`
- `COL.DISCRIMINATOR_CANDIDATE`

## 11) Контрактные коды: Entity
- `ENT.TABLE`
- `ENT.BASE_CLASS`
- `ENT.ABSTRACT`
- `ENT.AR_MODEL`
- `ENT.LAYER`
- `ENT.METAMODEL`

## 12) Контрактные коды: Yii1/Gx
- `YII1.RELATION_DECLARATION`
- `YII1.RELATION_KIND`
- `YII1.RELATION_OPTIONS`
- `YII1.SCOPE_DECLARATION`
- `YII1.DEFAULT_SCOPE`
- `YII1.BEHAVIOR`
- `YII1.NAMED_SCOPE`
- `GX.BASE_CLASS`
- `GX.RELATED_MODELS_HINT`

## 13) Контрактные коды: Relation
- `REL.DECLARED`
- `REL.KIND`
- `REL.TARGET`
- `REL.JOIN`
- `REL.JOIN_ORDER`
- `REL.USAGE_CARDINALITY`
- `REL.SQL_COMPLEXITY`
- `REL.DOCTRINE_CANDIDATE`
- `REL.OWNING_SIDE`
- `REL.INVERSE_SIDE`
- `REL.CUSTOM_REASON`

## 14) Контрактные коды: Inheritance/Discriminator
- `INH.ROOT`
- `INH.CHILD`
- `INH.STRATEGY`
- `INH.MAPPED_SUPERCLASS`
- `DISC.COLUMN`
- `DISC.VALUE_MAP`
- `DISC.SOURCE_CONFLICT`

## 15) Контрактные коды: Aggregate/SaveWithRelations
- `AGG.ROOT`
- `AGG.MEMBER`
- `AGG.PERSISTENCE_RULE`
- `AGG.TRANSACTION_BOUNDARY`
- `AR.SAVE_WITH_RELATIONS`
- `AR.CASCADE_SAVE_PLAN`

## 16) Контрактные коды: Decisions/Finalize
- `DEC.STATUS`
- `DEC.REPOSITORY_QUERY`
- `DEC.MANUAL_MAPPING`
- `DEC.READ_ONLY`
- `DEC.RATIONALE`
- `FIN.MAPPING_VALID`
- `FIN.EXPORT_READY`

## 17) Контрактные коды: Checks
- `CHK.NULL_MISMATCH`
- `CHK.TYPE_MISMATCH`
- `CHK.DEFAULT_MISMATCH`
- `CHK.PRECISION_SCALE_MISMATCH`
- `CHK.REL_COMPLEX`
- `CHK.REL_OWNING_SIDE_UNKNOWN`
- `CHK.REL_NON_PK_JOIN`
- `CHK.INHERITANCE_AMBIGUOUS`
- `CHK.DISCRIMINATOR_UNCLEAR`

## 18) `issue_code`
- `RELATION_SINGLETON_COLLECTION_MISMATCH`
- `RELATION_COMPLEX_SQL`
- `RELATION_NO_FK_SUPPORT`
- `RELATION_REQUIRES_REPOSITORY_QUERY`
- `COLUMN_TYPE_MISMATCH`
- `COLUMN_NULLABILITY_MISMATCH`
- `COLUMN_DEFAULT_MISMATCH`
- `INHERITANCE_AMBIGUOUS`
- `DISCRIMINATOR_UNCLEAR`
- `DISCRIMINATOR_SOURCE_CONFLICT`
- `MANUAL_MAPPING_REQUIRED`
- `COMPOSITE_FK_ORDER_AMBIGUOUS`
- `YII1_RELATION_ARRAY_UNPARSABLE`
- `RELATION_SCOPE_CONDITION_COMPLEX`
- `RELATION_THROUGH_CHAIN_COMPLEX`
- `METAMODEL_SHADOWING`
- `SAVE_WITH_RELATIONS_REQUIRED`
- `AGGREGATE_BOUNDARY_UNCLEAR`
- `ORACLE_SEQUENCE_MAPPING_REQUIRED`
- `MAPPING_INVALID`

## 19) Pipeline переходы
- `import_scope`: `objects(scope/entity/table)`
- `attach_metadata`: `object_facts(observed)`
- `run_introspection`: `object_facts(derived)`, `links(observed)`, `issues(open)`
- `build_candidates`: `object_facts(decision/target)`, `links(target)`
- `validate`: `object_facts(result)`, `issues(resolved|open)`
- `export`: `artifacts(*)`, `object_facts(result: FIN.EXPORT_READY)`

## 20) Цикл rector/AI
- `runs.stage`: `introspection|candidate|validate|export|reintrospection`
- каждый `run_id` фиксирует входные payload и выходные artifacts
- `issues` и `object_facts` связываются между `run_id` через `supersedes_fact_id` и `resolution_fact_id`
