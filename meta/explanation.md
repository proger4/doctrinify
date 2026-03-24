# explanation.md

## Очень простая легенда по "символам"

- `.` (точка) в коде типа `YII1.RELATION_DECLARATION` = "папка.файл" по смыслу.
  - Левая часть до точки = область (`YII1`, `AR`, `DB`, `COL`, `AGG`).
  - Правая часть после точки = конкретный факт внутри области.

- `_` (подчеркивание) в коде типа `RELATION_SCOPE_CONDITION_COMPLEX` = просто разделитель слов.

- `/` в `weight/confidence` = это не одно поле, а пара полей: `weight` и `confidence`.

- `:` в `source_kind: добавить ...` = "вот поле, а дальше что с ним сделать".

- `(...)` в `subject_type (model/relation/...)` = допустимые варианты значений.

- `,` = перечисление.

---

## 1) Что добавить в требования (простыми словами)

### `weight/confidence`
- `weight` = насколько "тяжелая" связь/факт (влияет на приоритет).
- `confidence` = насколько мы уверены, что это правда.
- Зачем: чтобы не все связи были "равно истинными".

### `direction_role`
- Роль направления связи.
- Пример: кто "owner", кто "target", кто "inverse".
- Зачем: UI и генератору важно понимать сторону связи.

### `run_id`
- ID конкретного прогона пайплайна (один запуск интроспекции/валидации).
- Зачем: чтобы видеть историю "что было в каком запуске".

### "нормализованный JSON для composite join mapping с сохранением порядка"
- `composite join` = join по нескольким колонкам (не одной).
- `нормализованный JSON` = всегда одна и та же форма данных.
- `с сохранением порядка` = пары колонок должны идти в фиксированном порядке, иначе можно сломать сравнение/генерацию.
- Пример формы:
  - `[{"local":"ORDER_ID","remote":"ORDER_ID","pos":1},{"local":"CUSTOMER_ID","remote":"CUSTOMER_ID","pos":2}]`

### `issues`
- Это отдельный список проблем (операционный backlog), чтобы проблемы не смешивались с фактами.

### `subject_type (model/relation/field/inheritance), subject_ref`
- `subject_type` = к чему относится проблема:
  - `model` (модель),
  - `relation` (связь),
  - `field` (поле),
  - `inheritance` (наследование).
- `subject_ref` = конкретная ссылка на объект (например `relation:App\Model\Order::items`).

### `detected_run_id, resolved_run_id, resolution_fact_id`
- `detected_run_id` = в каком запуске нашли проблему.
- `resolved_run_id` = в каком запуске закрыли проблему.
- `resolution_fact_id` = каким фактом/решением она закрыта.

---

## 2) Словарь контрактов: что означает каждая группа

### `YII1.RELATION_DECLARATION`
- `YII1` = источник/контекст Yii 1.
- `RELATION_DECLARATION` = как связь объявлена в модели.

### `YII1.RELATION_OPTIONS`
- Опции связи в Yii1 (`condition`, `joinType`, `order`, и т.п.).

### `YII1.SCOPE_DECLARATION`
- Описание scope'ов в модели (`scopes`, named scopes).

### `YII1.BEHAVIOR`
- Какие behaviors навешаны и как влияют на модель.

### `AR.SAVE_WITH_RELATIONS`
- `AR` = Active Record.
- Факт, что сохранение идет вместе со связанными сущностями (графом).

### `AGG.ROOT`, `AGG.MEMBER`, `AGG.PERSISTENCE_RULE`
- `AGG` = aggregate.
- `ROOT` = корень агрегата.
- `MEMBER` = участник агрегата.
- `PERSISTENCE_RULE` = правило сохранения агрегата.

### `DB.SEQUENCE`, `DB.TRIGGER`, `DB.DEFAULT`, `DB.INDEX`, `DB.CHECK`
- `DB` = база данных.
- `SEQUENCE` = генератор ID (особенно Oracle).
- `TRIGGER` = триггер БД.
- `DEFAULT` = дефолт значения колонки.
- `INDEX` = индекс.
- `CHECK` = check-constraint.

### `COL.PRECISION`, `COL.SCALE`, `COL.DEFAULT`, `COL.LENGTH`
- `COL` = колонка.
- `PRECISION` = общая точность числа.
- `SCALE` = количество знаков после точки.
- `DEFAULT` = дефолт колонки.
- `LENGTH` = длина строкового поля.

---

## 3) Issue codes: расшифровка по-человечески

### `YII1_RELATION_ARRAY_UNPARSABLE`
- Массив связи в Yii1 не удалось надежно разобрать.

### `RELATION_SCOPE_CONDITION_COMPLEX`
- У связи слишком сложные условия scope/where.

### `RELATION_THROUGH_CHAIN_COMPLEX`
- Цепочка `through` сложная/неоднозначная.

### `COMPOSITE_FK_ORDER_AMBIGUOUS`
- Неясен порядок колонок в составном внешнем ключе.

### `DISCRIMINATOR_SOURCE_CONFLICT`
- Конфликт источников по discriminator (SQL vs Yii vs вручную).

### `METAMODEL_SHADOWING`
- Metamodel "перекрывает" базовую модель, и непонятно что считать истиной.

### `SAVE_WITH_RELATIONS_REQUIRED`
- Нужна логика сохранения с зависимостями, обычного save мало.

### `AGGREGATE_BOUNDARY_UNCLEAR`
- Непонятна граница агрегата: кто root, кто member.

### `ORACLE_SEQUENCE_MAPPING_REQUIRED`
- Для Oracle нужно явно смэппить sequence, иначе ID-стратегия неверная.

---

## 4) Enum-справочники: что именно расширяем

### `source_kind: rector, doctrine_validate, runtime_trace`
- `rector` = источник — автоправки Rector.
- `doctrine_validate` = источник — результат валидации Doctrine.
- `runtime_trace` = источник — наблюдение из runtime/логов/трейса.

### `object_type: scope, aggregate, run, artifact, metamodel`
- `scope` = объект области миграции.
- `aggregate` = объект агрегата.
- `run` = объект конкретного прогона.
- `artifact` = объект экспортного/промежуточного артефакта.
- `metamodel` = объект слоя metamodel.

### `fact_kind: evidence`
- `evidence` = отдельный тип факта "это доказательство".
- Альтернатива: не вводить `evidence`, а хранить доказательства в `observed + source_ref_json`.

---

## 5) `link_type`: зачем каждый новый тип

### `model_baseclass`
- Связь "модель -> её базовый класс".

### `model_metamodel`
- Связь "модель -> metamodel слой".

### `aggregate_root_member`
- Связь "корень агрегата -> участник".

### `issue_subject`
- Связь "issue -> объект, к которому issue относится".

### `artifact_object`
- Связь "артефакт -> объект, из которого артефакт получен".

---

## Одна короткая мысль
Если совсем грубо: ты сейчас добавляешь в язык системы слова, которых ей не хватает, чтобы нормально описывать реальный legacy-проект, а не игрушечный пример.
