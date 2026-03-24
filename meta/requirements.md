Ниже — полное функциональное ТЗ уровня “можно отдавать в разработку”.

# Doctrine Migration Assistant v1

**Назначение:** одностраничный инструмент для короткого цикла миграции `Yii ActiveRecord → Doctrine ORM XML`.

---

## 1. Продуктовый контракт

Продукт принимает scope моделей, подтягивает SQL/Yii/agent-метаданные, строит кандидаты Doctrine mapping, отделяет обычные связи от custom relations, показывает blockers, проверяет готовность к Validate Schema и отдает экспортный bundle.

Главная рабочая единица интерфейса — **модель**.
Главная форма навигации — **левая IDE-подобная панель моделей**.
Главная логика интерфейса — **выбрал модель → увидел ее связи, кандидата, XML, blockers и export**.

---

## 2. Экранная карта продукта

Маршрут один:

* `/migration`

Но внутри него есть отдельные **виды** и **служебные окна**, которые тоже считаются частью ТЗ.

### 2.1. Основной экран `/migration`

Состоит из 5 зон:

* **Left Navigator** — список моделей в стиле Database Explorer IDE.
* **Top Action Bar** — pipeline-действия.
* **Center Workspace** — реестр моделей / связей.
* **Right Inspector** — подробности выбранной модели или relation.
* **Bottom Workbench** — blockers, validate, export.

### 2.2. Служебные окна и подвиды

Нужны как части продукта:

* `Import Scope Dialog`
* `Attach Metadata Dialog`
* `Rename Model / Note Popover`
* `Export Drawer`
* `Validate Result Drawer`
* `SQL Preview Modal`
* `XML Preview Fullscreen`
* `Prompt Extract Popover`

---

## 3. Главный layout

### 3.1. Общая сетка

Рекомендуемая композиция:

* **Left pane tabs:** 48 px, dark with icons, as VSCode extensions pane.
* **Left pane:** 320 px, resizable 280–420 px (allow dive into model)
* **Center pane:** fluid
* **Right pane:** 420 px optionally opened (as right drawer with close), resizable 360–560 px
* **Bottom pane:** 240 px, collapsible, expanded up to 420 px
* **Top bar:** 56 px fixed

Использовать IDE-подобную структуру с resizable panels.

---

## 4. Left Navigator — ключевая зона продукта

Это главная зона успеха инструмента.
Она должна ощущаться как список таблиц/моделей в инструментах БД IDE: быстрый, плотный, читаемый, с минимальной навигационной стоимостью.

## 4.1. Назначение

Left Navigator решает 6 задач:

1. показывает весь текущий scope;
2. позволяет быстро найти и выбрать модель;
3. позволяет добавить/убрать модель из scope;
4. позволяет переименовать модель для локального human-friendly отображения;
5. позволяет хранить заметку по модели;
6. визуально подсвечивает связанные модели при выборе текущей модели.

---

## 4.2. Структура Left Navigator

### Header

Содержит:

* заголовок `Models`
* счетчик моделей в scope
* кнопка `+ Add`
* кнопка `Import`
* кнопка `View`

### Search row

Содержит:

* строку поиска по FQCN / table / alias / note
* toggle `Focus relations`
* toggle `Compact`

### View preset switcher

Вместо россыпи мелких переключателей — один аккуратный preset switcher:

* `Explorer`
* `Tables`
* `FQCN`

#### Explorer

* primary label: `displayName ?? shortClassName`
* secondary label: `table`
* grouping: namespace tree

#### Tables

* primary label: `table`
* secondary label: `shortClassName`
* grouping: flat alphabetic by table

#### FQCN

* primary label: `fqcn`
* secondary label: optional `table`
* grouping: flat alphabetic by fqcn

### List body

Scrollable list/tree rows.

---

## 4.3. Структура строки модели

Каждая строка модели должна иметь следующую анатомию:

### Левая часть

* status lamp
* model icon
* primary label
* secondary label

### Центральная часть

* optional note glyph
* relation counters
* blockers count

### Правая часть

* relation highlight lamps
* overflow menu

---

## 4.4. Поля строки модели

```ts
type ModelNavigatorRow = {
  modelId: string
  fqcn: string
  shortName: string
  displayName?: string
  table?: string
  note?: string
  readiness: 'new' | 'ready' | 'review' | 'blocked'
  relationCount: number
  customRelationCount: number
  blockerCount: number
  selected: boolean
  dimmed: boolean
  relationHighlight: 'none' | 'outgoing' | 'incoming' | 'both' | 'custom' | 'blocking'
}
```

---

## 4.5. Inline actions в строке модели

Нужны 4 быстрых действия:

* `Rename`
* `Edit note`
* `Copy FQCN`
* `Remove from scope`

Открываются по:

* hover;
* right-click;
* overflow menu;
* keyboard shortcut.

### Rename

Редактирует `displayName`, не `fqcn`.

### Note

Открывает popover с multiline note.

---

## 4.6. Графическая подсветка связей в списке моделей

Это обязательная часть UX.

### Поведение

При выборе модели `M`:

* сама модель получает selected state;
* все связанные модели в Left Navigator получают relation-highlight;
* несвязанные модели могут быть слегка приглушены, если включен `Focus relations`.

### Типы подсветки

Подсветка должна быть одновременно:

* цветом левой полосы строки;
* лампочкой справа;
* optional soft background tint.

### Словарь relation highlight

* `outgoing` — из выбранной модели есть связь к этой модели
* `incoming` — из этой модели есть связь к выбранной модели
* `both` — есть связи в обе стороны или bidirectional pair
* `custom` — связь есть, но она custom
* `blocking` — связь есть, но по ней есть blocker
* `none` — связи нет

### Цвета relation highlight

* `outgoing` — blue `#2563EB`
* `incoming` — emerald `#059669`
* `both` — violet `#7C3AED`
* `custom` — amber `#D97706`
* `blocking` — red `#DC2626`
* `none` — transparent

### Приоритет цветов

Если у строки одновременно несколько состояний, приоритет такой:

1. `blocking`
2. `custom`
3. `both`
4. `outgoing`
5. `incoming`
6. `none`

### Лампочки

На правом краю строки — 3 микролампочки:

* **R** — relation topology
* **C** — custom relation indicator
* **B** — blocker indicator

#### Значения

* `R`: blue/green/violet/off
* `C`: amber/off
* `B`: red/off

Это позволяет даже в плотном списке моментально считывать связи без открытия inspector.

---

## 4.7. Контракт выбора модели

### Click по модели

* делает модель selected;
* обновляет center registry highlight;
* обновляет right inspector;
* пересчитывает подсветку related models;
* обновляет relations table фильтр.

### Hover по модели

* временно подсвечивает ее relations в center registry;
* не меняет selected state.

### Double click

* открывает модель в inspector и переводит inspector в `mapping`.

---

## 4.8. Add Model flow

Кнопка `+ Add` в Left Navigator открывает компактный drawer.

### В drawer:

* поле вставки FQCN list;
* quick add one model;
* autocomplete по известным fqcn;
* кнопка `Add to scope`.

### События:

* `model.added`
* `scope.updated`

---

## 4.9. Rename / Note flow

### Rename

* inline edit или popover;
* `displayName` используется только в UI;
* исходный `fqcn` не меняется.

### Note

* обычный текст;
* виден как note glyph в списке;
* в inspector note отображается отдельным блоком.

```ts
type ModelAnnotation = {
  modelId: string
  displayName?: string
  note?: string
  updatedAt: string
}
```

---

## 5. Top Action Bar

## 5.1. Состав

Слева направо:

* название scope
* count badges
* global search
* `Import Scope`
* `Attach Metadata`
* `Run Introspection`
* `Build Candidates`
* `Validate`
* `Export`

### Count badges

Показывают:

* models
* relations
* custom relations
* blockers
* ready models

---

## 5.2. Состояния кнопок

### Import Scope

Всегда активна.

### Attach Metadata

Активна после создания scope.

### Run Introspection

Активна, если есть scope и хотя бы один источник входных данных.

### Build Candidates

Активна после успешной интроспекции.

### Validate

Активна после генерации candidates.

### Export

Активна всегда после candidate generation, но показывает статус:

* `Partial export`
* `Ready export`

---

## 6. Center Workspace

Center Workspace — это реестр с двумя режимами:

* `Models`
* `Relations`

По умолчанию открыт `Models`.

---

## 6.1. Models View

### Колонки

* readiness
* display name / class
* table
* fields
* relations
* custom relations
* blockers
* xml status

### Поведение

* click row → open inspector model
* hover row → preview related relations
* secondary action → copy / export snippet

### XML status

* `not built`
* `candidate`
* `exportable`
* `invalid`

---

## 6.2. Relations View

### Колонки

* source
* target
* relation name
* rel type
* owning side
* classification
* errors
* xml candidate

### Classification

* `standard`
* `custom`
* `review`
* `blocked`

### Поведение

* click row → open inspector relation
* hover row → highlight source and target in left navigator
* if model selected and focus enabled → table auto-filters to adjacent relations

---

## 6.3. Center Workspace state

```ts
type WorkspaceView = 'models' | 'relations'

type WorkspaceState = {
  activeView: WorkspaceView
  searchQuery: string
  selectedModelId?: string
  selectedRelationId?: string
  hoveredModelId?: string
  hoveredRelationId?: string
  filterReadyOnly: boolean
  filterBlockedOnly: boolean
  filterCustomOnly: boolean
}
```

---

## 7. Right Inspector

Inspector показывает данные выбранной модели или relation.

Он всегда имеет 4 режима представления:

* `summary`
* `mapping`
* `xml`
* `diff`

---

## 7.1. Inspector для модели

### Header

* display name / fqcn
* table
* readiness badge
* source badges
* copy action cluster

### Copy action cluster

* `Copy FQCN`
* `Copy JSON`
* `Copy XML`
* `Copy Prompt`
* `Download`

### Summary view

Показывает:

* fqcn
* table
* base class
* source coverage
* fields count
* relations count
* custom relations count
* blockers count
* note

### Mapping view

Показывает:

* entity name
* table
* id mapping
* fields mapping
* associations
* inheritance / discriminator hints
* custom relation notes

### XML view

Показывает:

* read-only XML preview
* line numbers
* `Copy XML`
* `Download XML`
* `Open fullscreen`

### Diff view

Показывает нормализованное структурное сравнение:

* Yii meta
* SQL meta
* candidate mapping

Не code diff по всему тексту, а ключевой diff по сущностям:

* field name
* db type
* php type
* nullable
* default
* relation type
* join columns
* owning side

---

## 7.2. Inspector для relation

### Header

* `source → target`
* relType
* classification badge
* copy actions

### Summary view

* source
* target
* relation name
* raw relation evidence
* owning side
* join info
* standard/custom
* blockers

### Mapping view

* candidate association type
* join columns
* mappedBy / inversedBy
* cascade note
* fetch note
* why custom if custom

### XML view

* association XML preview only

### Diff view

* Yii relation meta
* SQL FK / join info
* agent payload
* candidate association

---

## 8. Bottom Workbench

Bottom panel состоит из 3 вкладок:

* `Blockers`
* `Validate`
* `Export`

---

## 8.1. Blockers tab

### Таблица blockers

Колонки:

* severity
* code
* subject
* message
* source
* fixability

### Поведение

* click → scroll/select subject in inspector
* hover → highlight related model/relation
* `Copy item`
* `Copy JSON`

---

## 8.2. Validate tab

### Что показывает

* validation status
* checklist
* summary counts
* optional test status
* failed checks

### Checklist items

* scope loaded
* introspection complete
* candidate mapping built
* id mapping determined
* nullable checked
* field types normalized
* owning side determined
* join columns valid
* custom relations extracted
* xml generated
* validate-schema ready

### Test status

Если передан test summary:

* `not provided`
* `passed`
* `failed`

---

## 8.3. Export tab

### Экспортный bundle

Показывает список файлов:

* `mapping_summary.json`
* `blockers_report.md`
* `custom_relations_report.md`
* `validate_checklist.md`
* `entity XML files`

### По каждому файлу

* preview
* copy
* download

### Дополнительно

В inspector и export panel должен быть доступен:

* `Copy Prompt Extract`

Это не отдельный режим UI, а дополнительный экспорт-формат выбранного объекта.

---

## 9. Все виды и служебные окна

## 9.1. Import Scope Dialog

Содержит:

* textarea для FQCN list
* drag-and-drop JSON
* preview normalized models
* кнопка `Create scope`

### Props

```ts
type ImportScopeDialogProps = {
  open: boolean
  onClose: () => void
  onSubmit: (payload: ScopeInputPayload) => void
}
```

---

## 9.2. Attach Metadata Dialog

Содержит 3 секции:

* Yii metadata
* SQL schema metadata
* Agent relation payload

В каждой секции:

* paste area
* upload
* parsed preview
* validation status

---

## 9.3. Rename / Note Popover

Содержит:

* `displayName` input
* `note` textarea
* save / cancel

---

## 9.4. SQL Preview Modal

Показывает:

* table DDL
* sequences
* indexes
* foreign keys
* copy actions

---

## 9.5. XML Fullscreen View

Показывает:

* full entity XML
* file name
* syntax highlight
* diff toggle against last generated candidate in current session

---

## 9.6. Prompt Extract Popover

Показывает короткую выжимку для агента:

* fqcn
* table
* candidate mapping summary
* blockers
* custom relation notes
* target outcome

Форматы:

* plain text
* markdown
* JSON

---

# 10. Детальный пользовательский flow

## 10.1. Flow: создание scope

1. Пользователь открывает `Import Scope`.
2. Вставляет список FQCN или загружает JSON.
3. Видит preview нормализованных моделей.
4. Нажимает `Create scope`.
5. Left Navigator заполняется моделями.
6. Каждая модель получает статус `new`.

## 10.2. Flow: attach metadata

1. Пользователь открывает `Attach Metadata`.
2. Загружает SQL, Yii, agent payload.
3. Видит parsed preview.
4. Подтверждает attach.
5. Top bar показывает attached badges.

## 10.3. Flow: introspection

1. Нажимает `Run Introspection`.
2. UI показывает progress.
3. После завершения:

    * модели получают table/source coverage;
    * relations появляются в registry;
    * blockers первичного уровня вычисляются;
    * в Left Navigator обновляются counters и status lamps.

## 10.4. Flow: выбор модели

1. Пользователь кликает модель слева.
2. Модель становится selected.
3. Связанные модели в списке подсвечиваются.
4. Center registry подсвечивает adjacent relations.
5. Inspector открывает `summary`.

## 10.5. Flow: build candidates

1. Нажимает `Build Candidates`.
2. Система строит mapping candidates.
3. У каждой модели появляется XML status.
4. У каждой relation появляется classification.

## 10.6. Flow: inspect relation

1. Пользователь переходит в `Relations`.
2. Кликает relation.
3. Inspector показывает relation candidate.
4. Если relation custom — причина явно написана.

## 10.7. Flow: validate

1. Пользователь открывает `Validate`.
2. Нажимает `Validate`.
3. Система собирает checklist.
4. Если есть blocking issues — validation fail.
5. Bottom panel показывает причины.

## 10.8. Flow: export

1. Пользователь открывает `Export`.
2. Видит список файлов bundle.
3. Может копировать по одному или скачать пакет.

---

# 11. Компонентная спецификация

## 11.1. `MigrationPage`

```ts
type MigrationPageProps = {
  initialScopeId?: string
}
```

### Internal state

```ts
type MigrationPageState = {
  scope?: MigrationScope
  models: ModelCard[]
  relations: RelationCard[]
  mismatches: Mismatch[]
  validation?: ValidationReport
  exportBundle?: ExportBundle
  ui: UIState
}
```

### Events

* `scope.imported`
* `metadata.attached`
* `introspection.started`
* `introspection.finished`
* `candidates.generated`
* `validation.finished`
* `export.ready`
* `model.selected`
* `relation.selected`

---

## 11.2. `ModelNavigator`

```ts
type ModelNavigatorProps = {
  models: ModelCard[]
  selectedModelId?: string
  searchQuery: string
  viewPreset: 'explorer' | 'tables' | 'fqcn'
  compact: boolean
  focusRelations: boolean
  onSelectModel: (id: string) => void
  onSearchChange: (q: string) => void
  onChangeViewPreset: (v: 'explorer' | 'tables' | 'fqcn') => void
  onToggleCompact: (v: boolean) => void
  onToggleFocusRelations: (v: boolean) => void
  onAddModel: () => void
  onRenameModel: (id: string) => void
  onEditNote: (id: string) => void
}
```

### Local state

* expanded groups
* hovered row
* context menu target

### Derived state

* filtered rows
* grouped rows
* relation highlight map

---

## 11.3. `ModelNavigatorRow`

```ts
type ModelNavigatorRowProps = {
  row: ModelNavigatorRow
  onClick: () => void
  onDoubleClick: () => void
  onRename: () => void
  onEditNote: () => void
  onCopyFqcn: () => void
  onRemove: () => void
}
```

---

## 11.4. `ActionBar`

```ts
type ActionBarProps = {
  scope?: MigrationScope
  counts: {
    models: number
    relations: number
    customRelations: number
    blockers: number
    readyModels: number
  }
  canRunIntrospection: boolean
  canBuildCandidates: boolean
  canValidate: boolean
  canExport: boolean
  onImportScope: () => void
  onAttachMetadata: () => void
  onRunIntrospection: () => void
  onBuildCandidates: () => void
  onValidate: () => void
  onExport: () => void
  onSearch: (q: string) => void
}
```

---

## 11.5. `ModelsTable`

```ts
type ModelsTableProps = {
  rows: ModelCard[]
  selectedModelId?: string
  onSelect: (id: string) => void
  onHover?: (id?: string) => void
}
```

---

## 11.6. `RelationsTable`

```ts
type RelationsTableProps = {
  rows: RelationCard[]
  selectedRelationId?: string
  selectedModelId?: string
  focusSelectedModel: boolean
  onSelect: (id: string) => void
  onHover?: (id?: string) => void
}
```

---

## 11.7. `InspectorPane`

```ts
type InspectorPaneProps = {
  selectedType?: 'model' | 'relation'
  model?: ModelCard
  relation?: RelationCard
  view: 'summary' | 'mapping' | 'xml' | 'diff'
  onChangeView: (v: 'summary' | 'mapping' | 'xml' | 'diff') => void
  onCopy: (format: CopyFormat) => void
  onDownload: (format: DownloadFormat) => void
}
```

### `CopyFormat`

```ts
type CopyFormat =
  | 'fqcn'
  | 'json'
  | 'sql'
  | 'xml'
  | 'prompt'
  | 'mapping'
  | 'diff'
```

---

## 11.8. `BlockersTable`

```ts
type BlockersTableProps = {
  rows: Mismatch[]
  onSelectSubject: (subjectKey: string) => void
  onCopyItem: (id: string) => void
}
```

---

## 11.9. `ValidatePanel`

```ts
type ValidatePanelProps = {
  report?: ValidationReport
  onRunValidate: () => void
}
```

---

## 11.10. `ExportPanel`

```ts
type ExportPanelProps = {
  bundle?: ExportBundle
  onCopyEntry: (entryId: string) => void
  onDownloadEntry: (entryId: string) => void
  onDownloadAll: () => void
}
```

---

# 12. Состояния данных

## 12.1. Основные типы

```ts
type MigrationScopeStatus =
  | 'draft'
  | 'loaded'
  | 'introspected'
  | 'candidate_built'
  | 'validated'
  | 'has_blockers'

type Readiness =
  | 'new'
  | 'ready'
  | 'review'
  | 'blocked'

type RelationClassification =
  | 'standard'
  | 'custom'
  | 'review'
  | 'blocked'

type ValidationStatus =
  | 'pass'
  | 'fail'

type Severity =
  | 'warning'
  | 'blocking'
```

---

## 12.2. Data model

```ts
type MigrationScope = {
  id: string
  name: string
  fqcnList: string[]
  status: MigrationScopeStatus
  annotations?: Record<string, ModelAnnotation>
}

type ModelCard = {
  id: string
  fqcn: string
  shortName: string
  displayName?: string
  note?: string
  table?: string
  baseClass?: string
  readiness: Readiness
  sourceCoverage: Array<'SQL' | 'YII' | 'AGENT'>
  fieldsCount: number
  relationsCount: number
  customRelationCount: number
  blockerCount: number
  yiiModelMeta?: unknown
  sqlMeta?: unknown
  mappingCandidate?: MappingCandidate
  xmlPreview?: string
}

type RelationCard = {
  id: string
  subjectKey: string
  sourceModelId: string
  targetModelId: string
  sourceFqcn: string
  targetFqcn: string
  relationName?: string
  relType?: string
  owningSide?: string
  classification: RelationClassification
  isCustom: boolean
  errors: string[]
  rawPayload?: unknown
  candidateDoctrineRelation?: unknown
}

type Mismatch = {
  id: string
  code: string
  severity: Severity
  subjectKey: string
  message: string
  sourceRefs: string[]
  fixability: 'auto' | 'manual' | 'investigate'
}

type MappingCandidate = {
  entityName: string
  table: string
  idMapping?: unknown
  fieldMappings: unknown[]
  associationMappings: unknown[]
  inheritanceHint?: unknown
  customRelationNotes?: string[]
  xmlStatus: 'not_built' | 'candidate' | 'exportable' | 'invalid'
}

type ValidationReport = {
  status: ValidationStatus
  checklist: Array<{
    code: string
    label: string
    passed: boolean
    message?: string
  }>
  blockers: Mismatch[]
  summary: {
    totalModels: number
    readyModels: number
    blockedModels: number
    standardRelations: number
    customRelations: number
  }
  tests?: {
    status: 'not_provided' | 'passed' | 'failed'
    failedCount?: number
  }
}

type ExportBundle = {
  status: 'partial' | 'ready'
  entries: Array<{
    id: string
    kind:
      | 'mapping_summary_json'
      | 'blockers_report_md'
      | 'custom_relations_report_md'
      | 'validate_checklist_md'
      | 'xml_file'
      | 'prompt_extract'
    name: string
    mimeType: string
    content: string
  }>
}
```

---

# 13. Словари бейджей и цветовая система

## 13.1. Readiness

* `new` — серый — модель еще не прошла интроспекцию.
* `ready` — зеленый — candidate mapping собран без blocking gaps.
* `review` — янтарный — candidate есть, но есть неоднозначности.
* `blocked` — красный — модель нельзя честно выгрузить в XML без разруливания blockers.

### Цвета

* `new` — `#94A3B8`
* `ready` — `#16A34A`
* `review` — `#D97706`
* `blocked` — `#DC2626`

---

## 13.2. Relation classification

* `standard` — зеленый — relation можно отдать как обычную Doctrine association.
* `custom` — фиолетовый — relation должна выйти в custom relation/report, а не в стандартный XML relation.
* `review` — янтарный — relation недостаточно определена.
* `blocked` — красный — relation не может быть собрана без блокирующего решения.

### Цвета

* `standard` — `#16A34A`
* `custom` — `#7C3AED`
* `review` — `#D97706`
* `blocked` — `#DC2626`

---

## 13.3. Source badges

* `SQL` — синий
* `YII` — индиго
* `AGENT` — циан

### Цвета

* `SQL` — `#2563EB`
* `YII` — `#4F46E5`
* `AGENT` — `#0891B2`

---

## 13.4. Severity

* `warning` — янтарный
* `blocking` — красный

---

## 13.5. XML status

* `not_built`
* `candidate`
* `exportable`
* `invalid`

### Цвета

* `not_built` — slate
* `candidate` — blue
* `exportable` — green
* `invalid` — red

---

# 14. Справочник mismatch codes

Каждый код имеет:

* `code`
* `label`
* `description`
* `severityDefault`
* `blocksXmlExport`
* `isCustomRelationCandidate`

Стартовый словарь:

```ts
const MISMATCH_DICTIONARY = {
  NULLABILITY_MISMATCH: {
    label: 'Nullability mismatch',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: false
  },
  TYPE_MISMATCH: {
    label: 'Type mismatch',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: false
  },
  OWNING_SIDE_UNKNOWN: {
    label: 'Owning side unknown',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: false
  },
  NON_PK_JOIN: {
    label: 'Non-PK join',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: true
  },
  JOIN_TABLE_REQUIRED: {
    label: 'Join table required',
    severityDefault: 'warning',
    blocksXmlExport: false,
    isCustomRelationCandidate: false
  },
  DISCRIMINATOR_UNCLEAR: {
    label: 'Discriminator unclear',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: false
  },
  INHERITANCE_AMBIGUITY: {
    label: 'Inheritance ambiguity',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: false
  },
  RELATION_WITH_SQL_CONDITION: {
    label: 'Relation with SQL condition',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: true
  },
  MISSING_TARGET_ENTITY: {
    label: 'Missing target entity',
    severityDefault: 'blocking',
    blocksXmlExport: true,
    isCustomRelationCandidate: false
  },
  SEQUENCE_MAPPING_REQUIRED: {
    label: 'Sequence mapping required',
    severityDefault: 'warning',
    blocksXmlExport: false,
    isCustomRelationCandidate: false
  }
}
```

---

# 15. API-контракты

Нужна session-модель, чтобы один цикл миграции был замкнут в одном объекте.

## 15.1. Создать session / scope

`POST /api/migration/session`

### Request

```json
{
  "name": "Orders wave 1",
  "fqcnList": [
    "App\\Model\\Order",
    "App\\Model\\Customer"
  ]
}
```

### Response

```json
{
  "sessionId": "mig_001",
  "scope": {
    "id": "mig_001",
    "name": "Orders wave 1",
    "fqcnList": [
      "App\\Model\\Order",
      "App\\Model\\Customer"
    ],
    "status": "loaded"
  }
}
```

---

## 15.2. Импорт scope JSON

`POST /api/migration/session/import-scope`

### Request

```json
{
  "scopeJson": {
    "name": "Orders wave 1",
    "fqcnList": [
      "App\\Model\\Order",
      "App\\Model\\Customer"
    ]
  }
}
```

---

## 15.3. Attach metadata

`POST /api/migration/session/:id/metadata`

### Request

```json
{
  "yiiModelMetadata": "...",
  "sqlSchemaMetadata": "...",
  "agentRelationPayload": [
    {
      "fqcnA": "App\\Model\\Order",
      "fqcnB": "App\\Model\\Customer",
      "relType": "ManyToOne",
      "owningSide": "Order",
      "errors": ["NON_PK_JOIN"],
      "metadata": {
        "confidence": "medium"
      }
    }
  ]
}
```

### Response

```json
{
  "ok": true,
  "attached": {
    "yii": true,
    "sql": true,
    "agent": true
  }
}
```

---

## 15.4. Run introspection

`POST /api/migration/session/:id/introspect`

### Response

```json
{
  "scopeStatus": "introspected",
  "models": [],
  "relations": [],
  "mismatches": []
}
```

---

## 15.5. Build candidates

`POST /api/migration/session/:id/candidates`

### Response

```json
{
  "scopeStatus": "candidate_built",
  "models": [],
  "relations": [],
  "mismatches": []
}
```

---

## 15.6. Validate

`POST /api/migration/session/:id/validate`

### Request

```json
{
  "includeTests": true
}
```

### Response

```json
{
  "report": {
    "status": "fail",
    "checklist": [],
    "blockers": [],
    "summary": {
      "totalModels": 12,
      "readyModels": 8,
      "blockedModels": 4,
      "standardRelations": 15,
      "customRelations": 3
    },
    "tests": {
      "status": "passed"
    }
  }
}
```

---

## 15.7. Export bundle

`POST /api/migration/session/:id/export`

### Response

```json
{
  "bundle": {
    "status": "partial",
    "entries": [
      {
        "id": "mapping_summary",
        "kind": "mapping_summary_json",
        "name": "mapping_summary.json",
        "mimeType": "application/json",
        "content": "{...}"
      }
    ]
  }
}
```

---

## 15.8. Model annotation

`PATCH /api/migration/session/:id/models/:modelId/annotation`

### Request

```json
{
  "displayName": "Order Aggregate",
  "note": "Проверить sequence и nullable по customer_id"
}
```

---

## 15.9. Prompt extract

`POST /api/migration/session/:id/prompt-extract`

### Request

```json
{
  "targetType": "model",
  "targetId": "model_order",
  "format": "markdown"
}
```

### Response

```json
{
  "content": "..."
}
```

---

# 16. Контракты копирования

## 16.1. Copy actions по блокам

### Header модели

* Copy FQCN
* Copy short class
* Copy table
* Copy JSON
* Copy prompt

### SQL block

* Copy DDL
* Copy sequences
* Copy indexes
* Copy full SQL bundle

### Mapping block

* Copy mapping JSON
* Copy candidate summary
* Copy prompt extract

### XML block

* Copy XML
* Download XML

### Diff block

* Copy diff JSON
* Copy diff markdown

### Blocker item

* Copy message
* Copy JSON
* Copy prompt extract

---

## 16.2. Форматы копирования

```ts
type CopyPayloadFormat =
  | 'text/plain'
  | 'application/json'
  | 'application/xml'
  | 'text/markdown'
  | 'text/sql'
```

---

# 17. Правила вычисления readiness

## 17.1. `ready`

Модель `ready`, если:

* есть table;
* собран id mapping;
* поля нормализованы;
* все стандартные relation-кандидаты определены;
* нет blocking mismatches;
* XML candidate собран.

## 17.2. `review`

Модель `review`, если:

* XML candidate есть;
* blockers нет;
* но есть неоднозначности warning-уровня.

## 17.3. `blocked`

Модель `blocked`, если:

* есть хотя бы один mismatch с `blocksXmlExport = true`.

## 17.4. `new`

Модель `new`, если:

* интроспекция не завершена.

---

# 18. Правила классификации relation

## 18.1. `standard`

Связь `standard`, если:

* relation type определен;
* target entity известна;
* join columns валидны;
* owning side определена;
* relation не требует SQL condition;
* не упирается в non-PK join.

## 18.2. `custom`

Связь `custom`, если:

* есть SQL condition;
* non-PK join;
* relation semantics не укладывается в обычный Doctrine mapping;
* связь должна уехать в repository/query layer.

## 18.3. `blocked`

Связь `blocked`, если:

* невозможно даже построить устойчивый candidate.

---

# 19. Правила relation highlight в Left Navigator

При selected model `M` UI обязан вычислить adjacency map.

## 19.1. Алгоритм

Для каждой модели `N`:

* если есть relation `M → N` и нет `N → M`: `outgoing`
* если есть relation `N → M` и нет `M → N`: `incoming`
* если есть оба направления: `both`
* если любая relation между `M` и `N` classified as `custom`: `custom`
* если любая relation между `M` и `N` имеет blocking mismatch: `blocking`

## 19.2. Визуальный контракт

* left border строки окрашен по relationHighlight;
* right lamps показывают topology/custom/blocker;
* при `Focus relations = on` несвязанные строки dimmed.

---

# 20. Техническая реализация UI

## 20.1. Основной стек

* **React 19.2 + TypeScript** как базовый UI-слой; React docs указывают latest version 19.2, а отдельный релиз React 19.2 опубликован официально. ([React][1])
* **TanStack Table v8** для registry-таблиц; это headless table/datagrid library, и документация отдельно описывает работу с virtualization через TanStack Virtual или другие virtual libs. ([TanStack][2])
* **Monaco Editor** для read-only JSON/XML/SQL preview и diff; официальный сайт Monaco прямо указывает, что это редактор, на котором построен VS Code, а API включает diff editor options. ([Microsoft на GitHub][3])

## 20.2. UI-библиотеки

Рекомендуемый набор:

* Radix UI / shadcn — popover, dialog, tabs, tooltip
* react-resizable-panels — IDE-подобный layout
* Zustand — клиентский state
* TanStack Query — data fetching и request lifecycle

## 20.3. XML и валидация Doctrine

Doctrine ORM поддерживает XML mapping через XML documents и валидирует mapping documents против XML Schema; это прямое основание для XML preview и XML export в продукте. В ограничениях Doctrine отдельно указано, что join columns, указывающие не на primary key, не поддерживаются корректно и такие случаи должны попадать в blockers/custom relation handling. Validate Schema tooling Doctrine — целевая проверка готовности этого пайплайна. ([Doctrine][4])

---

# 21. Acceptance criteria

Инструмент считается готовым, если:

1. пользователь может вставить FQCN list или импортировать scope JSON;
2. модели появляются в левом IDE-подобном навигаторе;
3. для модели можно задать displayName и note;
4. при выборе модели ее связи подсвечиваются в списке моделей цветом и лампочками;
5. интроспекция заполняет models и relations registry;
6. candidate mapping строится и виден в inspector;
7. relation получает classification `standard/custom/review/blocked`;
8. blockers собираются в отдельной таблице;
9. XML preview доступен по модели и relation;
10. все важные блоки можно копировать;
11. validate checklist собирается и показывает pass/fail;
12. export bundle можно копировать и скачивать.

---

# 22. Финальная формула для команды разработки

Собирать нужно **один экран с IDE-подобным левым навигатором моделей**, а не дашборд.
Главные компоненты:

* `ModelNavigator`
* `ActionBar`
* `ModelsTable / RelationsTable`
* `InspectorPane`
* `BottomWorkbench`
* `Import/Metadata/Export dialogs`

Главный UX-центр тяжести:

* список моделей слева;
* rename + note;
* смена вида списка;
* цветовая подсветка связанных моделей;
* лампочки topology/custom/blocker;
* inspector с `summary / mapping / xml / diff`;
* blockers + validate + export внизу.

Это ТЗ уже можно резать на frontend/backend задачи.