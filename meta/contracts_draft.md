Собрал рабочую версию без перегруза.

## Что фиксируем сразу

Беру за основу **4 таблицы** и **внешние словари** для contract codes и enum’ов.
То есть в БД пока **нет отдельных таблиц** `contracts` / `enums`: они живут как YAML/JSON/кодовые справочники, а в строках хранятся только коды.

### 4 коротких вопроса перед DDL

1. **Enum-справочники держим вне БД в v1?** Я бы сказал да.
2. **Отдельную таблицу `sources` пока не делаем?** Я бы сказал да: хватает `source_kind + source_ref` в фактах и links.
3. **Ключи фиксируем жестко?** Я бы сказал да:
   `table:profile`, `column:profile.user_id`, `entity:App\Model\Profile`, `relation:App\Model\Profile::user`.
4. **`issue_code` и `contract_code` — разные словари?** Я бы сказал да, это важное разделение.

---

# Вариант 3

## 1) Таблицы

### `objects`

Реестр объектов.

| Поле              | Смысл                | Завязка                   |
| ----------------- | -------------------- | ------------------------- |
| `object_key`      | PK объекта           | фиксированный key format  |
| `object_type`     | тип объекта          | `enum.object_type`        |
| `canonical_name`  | нормальное имя       | —                         |
| `root_object_key` | корень ветки         | FK → `objects.object_key` |
| `status`          | общий статус объекта | `enum.case_status`        |

### `object_facts`

Факты, решения, target и результаты.

| Поле            | Смысл                      | Завязка                                   |
| --------------- | -------------------------- | ----------------------------------------- |
| `fact_id`       | PK                         | —                                         |
| `object_key`    | к какому объекту относится | FK → `objects.object_key`                 |
| `contract_code` | какой именно факт          | `dict.contract_code`                      |
| `fact_kind`     | природа факта              | `enum.fact_kind`                          |
| `value_json`    | значение факта             | value shape зависит от `contract_code`    |
| `source_kind`   | откуда взято               | `enum.source_kind`                        |
| `source_ref`    | где найдено                | —                                         |
| `status`        | статус факта               | `enum.fact_status`                        |
| `confidence`    | уверенность                | `enum.confidence_level` или small numeric |
| `run_id`        | партия анализа             | —                                         |

### `links`

Связи между объектами.

| Поле              | Смысл           | Завязка                   |
| ----------------- | --------------- | ------------------------- |
| `link_id`         | PK              | —                         |
| `from_object_key` | источник        | FK → `objects.object_key` |
| `link_type`       | тип связи       | `enum.link_type`          |
| `to_object_key`   | цель            | FK → `objects.object_key` |
| `link_kind`       | observed/target | `enum.link_kind`          |
| `status`          | статус связи    | `enum.link_status`        |
| `payload_json`    | доп. детали     | зависит от `link_type`    |

### `issues`

Проблемы и контур разрешения.

| Поле             | Смысл                   | Завязка                   |
| ---------------- | ----------------------- | ------------------------- |
| `issue_id`       | PK                      | —                         |
| `object_key`     | по какому объекту issue | FK → `objects.object_key` |
| `issue_code`     | код проблемы            | `dict.issue_code`         |
| `severity`       | важность                | `enum.severity`           |
| `status`         | состояние issue         | `enum.issue_status`       |
| `summary`        | коротко в чем проблема  | —                         |
| `execution_mode` | как чинить              | `enum.execution_mode`     |
| `decision_note`  | что решили              | —                         |

---

## 2) Словарь контрактов

Ниже — **ядро**, без которого схема уже работает.

### Таблица / колонка

* `TBL.PK`
* `TBL.ID_STYLE`
* `COL.TYPE`
* `COL.NULLABLE`
* `COL.UNIQUE`
* `COL.FK_TARGET`
* `COL.DISCRIMINATOR_CANDIDATE`

### Сущность

* `ENT.TABLE`
* `ENT.BASE_CLASS`
* `ENT.ABSTRACT`
* `ENT.AR_MODEL`

### Relation

* `REL.DECLARED`
* `REL.KIND`
* `REL.TARGET`
* `REL.JOIN`
* `REL.USAGE_CARDINALITY`
* `REL.SQL_COMPLEXITY`
* `REL.DOCTRINE_CANDIDATE`

### Inheritance / discriminator

* `INH.ROOT`
* `INH.CHILD`
* `INH.STRATEGY`
* `INH.MAPPED_SUPERCLASS`
* `DISC.COLUMN`
* `DISC.VALUE_MAP`

### Решения / финализация

* `DEC.STATUS`
* `DEC.REPOSITORY_QUERY`
* `DEC.MANUAL_MAPPING`
* `DEC.READ_ONLY`
* `FIN.MAPPING_VALID`

### Проверки

* `CHK.NULL_MISMATCH`
* `CHK.TYPE_MISMATCH`
* `CHK.REL_COMPLEX`
* `CHK.INHERITANCE_AMBIGUOUS`

---

## 3) Словарь issue codes

Я бы стартовал с таким коротким набором:

* `RELATION_SINGLETON_COLLECTION_MISMATCH`
* `RELATION_COMPLEX_SQL`
* `RELATION_NO_FK_SUPPORT`
* `RELATION_REQUIRES_REPOSITORY_QUERY`
* `COLUMN_TYPE_MISMATCH`
* `COLUMN_NULLABILITY_MISMATCH`
* `INHERITANCE_AMBIGUOUS`
* `DISCRIMINATOR_UNCLEAR`
* `MANUAL_MAPPING_REQUIRED`
* `ORPHAN_TABLE`
* `DEAD_TABLE_CANDIDATE`
* `MAPPING_INVALID`

---

## 4) Enum-справочники

### Технические enum’ы таблиц

* `object_type` = `table, column, entity, relation, inheritance, discriminator`
* `case_status` = `ok, gap, conflict, manual, drop, unsupported`
* `fact_kind` = `observed, derived, decision, target, result`
* `fact_status` = `active, superseded, rejected`
* `source_kind` = `sql, yii, usage, uml, manual, llm`
* `link_kind` = `observed, target`
* `link_status` = `active, rejected`
* `severity` = `low, medium, high, blocker`
* `issue_status` = `open, accepted, resolved, wont_fix`
* `execution_mode` = `rector, ai_batch, manual, blocked`
* `confidence_level` = `low, medium, high`

### Enum’ы значений внутри контрактов

* `relation_kind` = `belongs_to, has_one, has_many, many_many`
* `cardinality_kind` = `single, collection, mixed, unknown`
* `inheritance_strategy` = `none, single_table, joined, mapped_superclass, unknown`
* `id_style` = `auto_increment, uuid, manual, composite`
* `decision_status` = `auto, review, manual, dead, split, defer`

---

## 5) Минимальные `link_type`

* `entity_table`
* `relation_owner`
* `relation_target`
* `relation_local_column`
* `relation_remote_column`
* `inheritance_root`
* `inheritance_child`
* `discriminator_column`

---

## 6) Как это работает в процессе

### Исследование

Пишем:

* `objects`
* `object_facts` с `fact_kind = observed`
* `links` с `link_kind = observed`
* `issues`

### Решение

Добавляем:

* `object_facts` с `fact_kind = decision`
* `object_facts` с `fact_kind = target`
* `links` с `link_kind = target`

### Проверка после рефакторинга

Добавляем:

* `object_facts` с `fact_kind = result`
* закрываем `issues`

---

## 7) Откуда брать задачи и summary

### Пул задач на Rector / AI

Источник задач:

* `issues.status = open`
* плюс `DEC.*` факты

Разрез:

* `issue_code`
* `execution_mode`
* `object_type`

### Breakdown

Делается очень просто:

* сколько `relation` в `conflict`
* сколько `entity` в `manual`
* сколько `issues` по `RELATION_COMPLEX_SQL`
* 3–5 примеров `object_key` на каждый тип проблемы

---

## 8) Финальная короткая фиксация

### Таблицы

* `objects`
* `object_facts`
* `links`
* `issues`

### Принцип

* объект живет в `objects`
* факт/решение/target/result живет в `object_facts`
* связь живет в `links`
* проблема и способ починки живет в `issues`

### Не делаем в v1

* отдельные DB-таблицы под все enum’ы
* отдельную таблицу `sources`
* отдельную таблицу `contracts`

Они пока должны быть **внешними словарями**, иначе модель рано распухнет.

Если эта фиксация подходит, следующий шаг уже прямой: я сведу это в компактный DDL для 4 таблиц и короткие YAML-справочники `contract_code`, `issue_code`, `enum`.
