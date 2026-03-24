# Комментарии к `contracts_draft.md`

## Контекст разбора
Разбор сделан по трём источникам:
- `meta/contracts_draft.md`
- UI-прототип: `ui/src/app/App.tsx`, `ui/src/app/components/ModelCard.tsx`, `ui/src/app/components/ModelsCardsList.tsx`
- PHP-интроспекция и пайплайн: `src/Tools/*`, `src/Service/OrmGeneratorService.php`

## Общая оценка
Черновик удачный как v1-каркас для SQLite: простая ядровая модель `objects/object_facts/links/issues`, отделение словарей `contract_code` и `issue_code`, поддержка pipeline-стадий через `fact_kind`.

Для целевого кейса (большой Yii1 legacy на GxActiveRecord, иерархии, Oracle, сложные relations, итерации rector/AI/re-export) текущая версия **частично достаточна для старта**, но **недостаточна для полноценной миграции без расширений**.

## Проверка ключевых аспектов

| Аспект | Статус | Комментарий |
|---|---|---|
| Специфика Yii metadata | Частично | Есть место для фактов `ENT.*`, `REL.*`, `DISC.*`, но нет обязательных контрактов под Yii1 `relations()`/scopes/behaviors/`defaultScope()`/`named scopes`/`through`/`together`/`stat`.
| Oracle | Частично | Модель допускает фиксацию Oracle-фактов, но в словаре контрактов не закреплены sequence/trigger/default/index/check/comment/synonym/quoted identifiers.
| Дерево моделей и абстрактные классы | Частично | Есть `ENT.BASE_CLASS`, `ENT.ABSTRACT`, `INH.*`, но нет явного контракта для уровней `GxActiveRecord -> _base -> model -> metamodel` и роли каждого уровня в генерации.
| Доп. слои наследования (metamodels) | Нет | В `object_type` нет отдельного типа/признака слоя. В UI слой `metamodels` уже присутствует, в контрактах — нет.
| Композитные внешние ключи | Частично | `REL.JOIN` можно использовать, но не зафиксирована нормализованная форма порядка join-пар, роли колонок и источника истинности при конфликте.
| Колонки-дискриминаторы | Частично | Есть `DISC.COLUMN`, `DISC.VALUE_MAP`, `INH.STRATEGY`, но нет контрактов для конфликтов discriminator между SQL/Yii/ручным решением.
| Несоответствия полей и метаданных | Частично | Есть `CHK.NULL_MISMATCH`, `CHK.TYPE_MISMATCH`, но мало для legacy: нет default/length/precision/scale/collation/enum-domain mismatches.
| Агрегаты и `SaveWithRelations` | Нет | Нет контрактов для границ агрегата, графа сохранения, каскадных правил, порядка сохранения, transactional boundary.
| Цикл rector + AI + повторная проверка | Частично | Есть `run_id` и `fact_kind=result`, но нет формального контракта итерации (run stage, baseline, artifact snapshot, diff between runs).

## Что уже хорошо покрыто
- Единый реестр объектов и фактов хорошо ложится на UI-поток `Scope -> Introspection -> Candidates -> Validate -> Export`.
- Разделение `issue_code` и `contract_code` правильно для аналитики и автоматизации.
- `fact_kind` (`observed/derived/decision/target/result`) подходит под обратную связь с Rector/AI.
- `links` как отдельная таблица — правильная база для relation-graph и подсветки в UI.

## Критичные пробелы относительно legacy Yii1

1. Нет обязательного блока контрактов именно под Yii1 AR API:
- `relations()` c `BELONGS_TO/HAS_ONE/HAS_MANY/MANY_MANY/STAT`
- `scopes()` / `defaultScope()` / named scopes
- `with()/together()/through`-цепочки
- relation options (`condition`, `on`, `order`, `group`, `having`, `joinType`, `select`)

2. Нет модели provenance-конфликта между источниками:
- SQL vs Yii vs usage vs AI vs manual
- приоритеты, tie-break policy, фиксированное `decision rationale`

3. Нет отдельного описания графа агрегатов:
- aggregate root
- children entities
- persistence graph
- `SaveWithRelations` strategy

4. Нет контрактов на качество и повторяемость итераций:
- baseline перед запуском rector
- post-run verify
- artifact linkage (`prompt`, `patch`, `xml`, `report`)

5. Недостаточная детализация issues для реального legacy:
- нет кодов для composite join ambiguity, metamodel shadowing, inherited tableName conflict, relation alias collision, non-deterministic scope condition.

## Комментарии по разделам `contracts_draft.md`

### 1) Таблицы

#### `objects`
Сильная сторона: единый ключевой каталог.

Что добавить в требования:
- поля: `layer` (`gx_base`, `base`, `model`, `metamodel`), `parent_object_key`, `origin`.
- индекс: `(object_type, canonical_name)`.
- политика ключей для quoted/case-sensitive Oracle имён.

#### `object_facts`
Сильная сторона: универсальная факт-таблица.

Что добавить в требования:
- `source_ref` разделить на `source_ref` + `source_ref_json`.
- `evidence_hash`, `supersedes_fact_id`, `created_at`.
- уникальность хотя бы на уровне `(object_key, contract_code, fact_kind, run_id, source_kind, evidence_hash)`.

#### `links`
Сильная сторона: гибкий граф.

Что добавить в требования:
- `weight/confidence`, `direction_role`, `run_id`.
- нормализованный JSON для composite join mapping с сохранением порядка.

#### `issues`
Сильная сторона: отделённый operational backlog.

Что добавить в требования:
- `subject_type` (`model/relation/field/inheritance`), `subject_ref`.
- `detected_run_id`, `resolved_run_id`, `resolution_fact_id`.

### 2) Словарь контрактов
Текущий словарь — хорошее ядро, но для legacy не хватает следующих групп:
- `YII1.RELATION_DECLARATION`
- `YII1.RELATION_OPTIONS`
- `YII1.SCOPE_DECLARATION`
- `YII1.BEHAVIOR`
- `AR.SAVE_WITH_RELATIONS`
- `AGG.ROOT`, `AGG.MEMBER`, `AGG.PERSISTENCE_RULE`
- `DB.SEQUENCE`, `DB.TRIGGER`, `DB.DEFAULT`, `DB.INDEX`, `DB.CHECK`
- `COL.PRECISION`, `COL.SCALE`, `COL.DEFAULT`, `COL.LENGTH`

### 3) Issue codes
Набор короткий и полезный, но требует расширения под реальные кейсы:
- `YII1_RELATION_ARRAY_UNPARSABLE`
- `RELATION_SCOPE_CONDITION_COMPLEX`
- `RELATION_THROUGH_CHAIN_COMPLEX`
- `COMPOSITE_FK_ORDER_AMBIGUOUS`
- `DISCRIMINATOR_SOURCE_CONFLICT`
- `METAMODEL_SHADOWING`
- `SAVE_WITH_RELATIONS_REQUIRED`
- `AGGREGATE_BOUNDARY_UNCLEAR`
- `ORACLE_SEQUENCE_MAPPING_REQUIRED`

### 4) Enum-справочники
Нужны расширения:
- `source_kind`: добавить `rector`, `doctrine_validate`, `runtime_trace`.
- `object_type`: добавить `scope`, `aggregate`, `run`, `artifact`, `metamodel`.
- `fact_kind`: добавить `evidence` (или зафиксировать в `observed` + `source_ref_json`).

### 5) `link_type`
Минимум ок, но для UI и legacy добавить:
- `model_baseclass`
- `model_metamodel`
- `aggregate_root_member`
- `issue_subject`
- `artifact_object`

### 6-8) Процесс, задачи, summary
Логика правильная, но не хватает формализации итеративного цикла:
- `run.start` / `run.finish`
- связка артефактов экспорта с `run_id`
- re-introspection после rector/AI с хранением diff от предыдущего run

## Сверка с текущим PHP-интроспектором

### Что уже поддерживается в коде
- Oracle schema dump parser (таблицы, PK/FK, composite keys, sequences, unique constraints).
- Иерархии классов, абстрактные классы, частичная STI-логика.
- Из моделей: `tableName`, `primaryKey`, `rules`, `attributeLabels`, `hasOne/hasMany`, query modifiers в relation chain.
- Частичный discriminator extraction (`find()->where(['type' => ...])`, `beforeSave()` присвоение `type`).

### Что не поддерживается сейчас
- Yii1 `relations()` array-формат и его семантика.
- GxActiveRecord-специфика и metamodel слои как first-class contracts.
- `SaveWithRelations`/aggregate persistence semantics.
- Продвинутый merge SQL/Yii/usage/AI с explainable conflict resolution.

## Итог
`contracts_draft.md` подходит как база v1, но для заявленной миграции legacy Yii1→Doctrine его нужно расширить по 5 направлениям:
- Yii1/Gx contracts,
- Oracle-детализация,
- aggregate/SaveWithRelations,
- multi-source conflict resolution,
- run/artifact feedback loop для rector+AI.
