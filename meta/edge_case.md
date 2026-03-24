# Спецификация edge cases для Doctrine Migration Assistant

## 1. Назначение

Этот документ фиксирует единый контракт для:

- обнаружения edge cases в интроспекции;
- группировки edge cases вокруг `subject_key + anchor_key`;
- отображения edge cases в UI;
- добавления новых типов edge cases без поломки схемы;
- ручного промоутинга `unknown` кейсов в новые явные типы.

Цель: обеспечить расширяемый цикл `find -> triage -> define new type -> re-run`.

---

## 2. Термины

- `mismatch` — конкретное расхождение фактов.
- `edge case` — mismatch с повышенным риском авто-решения и/или недостаточной определенностью.
- `subject_key` — точный объект, к которому относится кейс.
- `anchor_key` — агрегирующий ключ для группировки нескольких кейсов в одну смысловую группу.
- `evidence` — наблюдаемые факты из источников `sql|yii|usage|agent|manual`.
- `unknown edge case` — кейс, который не проходит по известным правилам, но аномалия обнаружена.

---

## 3. Ключевые требования

1. Любой edge case обязан иметь `subject_key` и `anchor_key`.
2. UI обязан строить группы по `anchor_key`.
3. Контракт обязан принимать неизвестные типы через `UNKNOWN_EDGE_CASE`.
4. Известные типы edge cases определяются явными правилами (раздел 9).
5. Решение `auto` допускается только при достаточном evidence (минимум 2 независимых источника).
6. Все спорные/недостаточно подтвержденные кейсы уходят в `manual` или `defer`.

---

## 4. Контракт данных

### 4.1. EdgeIssue

```ts
type EvidenceSource = 'sql' | 'yii' | 'usage' | 'agent' | 'manual'

type EdgeEvidence = {
  source: EvidenceSource
  ref: string             // path:line, object key, sql fragment id, etc.
  summary: string         // короткий факт
  confidence?: number     // 0..1
  payload_json?: unknown  // сырые детали источника
}

type EdgeIssue = {
  id: string
  run_id: string
  code: string            // известный code или UNKNOWN_EDGE_CASE
  severity: 'warning' | 'blocking'
  decision: 'auto' | 'manual' | 'defer'
  fixability: 'auto' | 'manual' | 'investigate'

  subject_type:
    | 'model'
    | 'relation_family'
    | 'relation_variant'
    | 'field'
    | 'usage_hotspot'
    | 'inheritance'
    | 'discriminator'
  subject_key: string
  anchor_key: string

  message: string
  impact: string
  claim: string
  evidence: EdgeEvidence[]

  // для unknown и для последующего промоутинга
  fingerprint: string
  triage_status?: 'new' | 'triaged' | 'promoted' | 'dismissed'
  promoted_code?: string

  // обратные связи для агрегирования
  related_subject_keys?: string[]
}
```

### 4.2. EdgeGroup

```ts
type EdgeGroup = {
  id: string
  run_id: string
  anchor_key: string
  title: string
  group_kind: 'relation' | 'model' | 'usage' | 'inheritance' | 'mixed'
  issue_ids: string[]
  counters: {
    total: number
    blocking: number
    warning: number
    manual: number
    unknown: number
  }
  status: 'ok' | 'review' | 'blocked'
}
```

---

## 5. Форматы ключей

### 5.1. subject_key

- `model:<FQCN>`
- `field:<FQCN>#<field>`
- `relation-family:<SRC_FQCN>-><DST_FQCN>:<join_sig_hash>`
- `relation-variant:<SRC_FQCN>::<method>#<variant_hash>`
- `usage:<file>#L<line>:<usage_hash>`
- `inheritance:<ROOT_FQCN>`
- `discriminator:<FQCN>:<column>`

### 5.2. anchor_key (правило агрегирования)

- для `relation_variant` -> соответствующий `relation-family:*`
- для `relation_family` -> сам ключ
- для `usage_hotspot`, если есть привязка к relation -> `relation-family:*`
- для `usage_hotspot`, если relation не определена -> `model:<FQCN>`
- для `field` -> `model:<FQCN>`
- для `inheritance/discriminator` -> `inheritance:<ROOT_FQCN>` либо `discriminator:<FQCN>:<column>`

---

## 6. Правила принятия решений

1. `auto` разрешен только если:
   - минимум 2 независимых источника из `sql|yii|usage`;
   - нет конфликтующего evidence;
   - `severity != blocking` или явно разрешено policy.
2. `manual` ставится, если:
   - только 1 источник evidence;
   - или источники конфликтуют;
   - или кейс `UNKNOWN_EDGE_CASE`.
3. `defer` ставится, если:
   - нужно больше данных/доступа к закрытому контуру;
   - или кейс не влияет на текущий rollout wave.

---

## 7. Unknown-first стратегия

### 7.1. Обязательный fallback code

- `UNKNOWN_EDGE_CASE`

### 7.2. Когда ставить

Ставить `UNKNOWN_EDGE_CASE`, если:

- обнаружена аномалия, но не сработало ни одно правило из раздела 9;
- payload нестандартный/непарсабельный;
- известный код есть, но confidence ниже порога (по policy проекта).

### 7.3. Что обязательно заполнить

- `subject_key`
- `anchor_key`
- `fingerprint`
- `message`
- `impact`
- `evidence[]` (минимум 1 факт)
- `triage_status = new`

### 7.4. Промоутинг unknown -> known

1. Триаж объединяет unknown по `fingerprint + anchor_key`.
2. Аналитик вводит новый `code` и правило детекции.
3. Кейсы получают `promoted_code`, `triage_status=promoted`.
4. Новый code добавляется в словарь и в раздел 9.
5. Следующий run уже детектит кейс как known type.

---

## 8. UI контракт для видимости edge cases

### 8.1. Обязательные представления

1. `Edge Groups` (таблица групп по `anchor_key`).
2. `Issues in Group` (все issue внутри выбранной группы).
3. `Evidence` (факты по каждому issue).
4. `Unknown Queue` (`code=UNKNOWN_EDGE_CASE`).

### 8.2. Обязательные фильтры

- `edge_only`
- `blocked_only`
- `manual_decision_required`
- `unknown_only`
- `anchor_kind` (`relation|model|usage|inheritance|mixed`)

### 8.3. Минимальные колонки Edge Groups

- `title`
- `anchor_key`
- `total`
- `blocking`
- `manual`
- `unknown`
- `status`

### 8.4. Минимальные колонки Issues in Group

- `severity`
- `code`
- `subject_key`
- `message`
- `sources`
- `decision`
- `fixability`

---

## 9. Известные edge-case типы и явные правила определения

Ниже список v1. Любой код без выполненного правила не должен ставиться.

### 9.1. `RELATION_COMPLEX_SQL`

Ставить, если у relation обнаружен любой SQL modifier из:

- `where`, `andWhere`, `orWhere`
- `onCondition`
- `joinWith`, `innerJoin`, `leftJoin`, `rightJoin`
- `orderBy`, `addOrderBy`, `groupBy`, `having`
- `viaTable`
- options: `condition`, `order`, `joinType`

Минимальный evidence:

- `yii` (AST relation declaration) + список modifiers.

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.2. `RELATION_FK_MISMATCH`

Ставить, если:

- relation declared в Yii;
- mapping relation не подтверждается FK из SQL schema
  или локальные/удаленные join columns расходятся.

Минимальный evidence:

- `yii` relation mapping
- `sql` FK metadata

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.3. `OWNING_SIDE_UNKNOWN`

Ставить, если:

- обнаружен bidirectional relation pair;
- невозможно однозначно определить owning side по mapping/usage.

Минимальный evidence:

- `yii` declarations обеих сторон
- `sql` FK или `usage` факты (как минимум один из них)

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.4. `DISCRIMINATOR_UNCLEAR`

Ставить, если:

- найдено поле-кандидат discriminator (обычно `type`);
- сигналы недостаточны, чтобы решить это STI-дискриминатор или бизнес-флаг.

Минимальный evidence:

- минимум 1 сигнал из:
  - `find()->where(['type'=>...])`
  - `beforeSave` assignment `type`
  - `afterFind` logic by `type`
  - `beforeValidate`/behaviors, влияющие на `type`

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.5. `DISCRIMINATOR_SOURCE_CONFLICT`

Ставить, если:

- есть минимум 2 сигнала discriminator;
- сигналы конфликтуют по смыслу/значениям/роли поля.

Минимальный evidence:

- минимум 2 источника из `yii|usage|manual`, с явным конфликтом.

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.6. `CYCLIC_RELATION_CASE`

Ставить, если:

- в relation graph найден цикл, затрагивающий текущий subject;
- цикл влияет на безопасную генерацию mapping/validate path.

Минимальный evidence:

- `derived` graph cycle proof (путь узлов)
- плюс хотя бы один `yii` или `usage` факт о реальном использовании.

Решение по умолчанию:

- `severity=warning` (повышать до `blocking`, если ломает candidate generation/validate)
- `decision=manual`

### 9.7. `MISSING_PK_OR_FK_CASE`

Ставить, если:

- отсутствует PK или необходимый FK для стабильного mapping;
- либо ключ присутствует, но невалиден/нечитаем для детекции.

Минимальный evidence:

- `sql` schema факт отсутствия/невалидности ключа
- `yii` relation/table declaration (если есть)

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.8. `RELATION_TYPE_MISMATCH`

Ставить, если:

- relation type по Yii (`hasOne/hasMany/...`) не согласуется с SQL cardinality/FK;
- или usage устойчиво указывает другую кардинальность.

Минимальный evidence:

- `yii` relation kind
- `sql` cardinality факт или `usage` cardinality факт

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.9. `MODEL_TABLE_MISMATCH`

Ставить, если:

- `tableName` модели не совпадает с резолвом таблицы;
- или модель указывает таблицу, которой нет в schema.

Минимальный evidence:

- `yii` model table fact
- `sql` table existence fact

Решение по умолчанию:

- `severity=blocking`
- `decision=manual`

### 9.10. `FIELD_COLUMN_MISMATCH`

Ставить, если:

- поле/колонка расходятся по имени, nullable, типу, default, precision/scale/length;
- и это влияет на mapping candidate.

Минимальный evidence:

- `yii` field/rules/labels fact
- `sql` column metadata fact

Решение по умолчанию:

- `severity=blocking` для type/nullability;
- `severity=warning` для не-блокирующих расхождений;
- `decision=manual` (или `auto` при строго однозначном правиле и достаточном evidence).

---

## 10. Алгоритм детекции (high-level)

1. Снять raw факты из `sql`, `yii`, `usage`, `agent`.
2. Нормализовать facts в canonical форму.
3. Применить правила раздела 9 в фиксированном порядке.
4. Для каждого сработавшего правила создать `EdgeIssue`.
5. Для несработавших, но аномальных случаев создать `UNKNOWN_EDGE_CASE`.
6. Построить `EdgeGroup[]` по `anchor_key`.
7. Вычислить group status:
   - `blocked`, если есть `blocking`;
   - `review`, если нет `blocking`, но есть `warning/manual/unknown`;
   - `ok`, если issues нет.

---

## 11. Совместимость с текущим v1 интерфейсом

Для обратной совместимости:

- текущий `Mismatch` остается плоским представлением;
- `subjectKey` маппится из `subject_key`;
- `sourceRefs` маппится из `evidence[].ref`;
- `fixability` и `severity` сохраняются;
- `EdgeGroup` добавляется как новое представление, не ломая старое.

---

## 12. Критерии готовности (DoD для edge-case контура)

1. Любой issue имеет `subject_key` и `anchor_key`.
2. UI умеет показывать `Edge Groups` и `Unknown Queue`.
3. `UNKNOWN_EDGE_CASE` попадает в triage без потери payload.
4. Известные коды определяются только правилами раздела 9.
5. Есть путь промоутинга unknown -> known code.
6. Повторный run переиспользует новые правила и снижает долю unknown.

