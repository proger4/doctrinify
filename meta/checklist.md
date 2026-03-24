Чек-лист для проверки постановки задачи
Правило ответа агента

На каждый пункт отвечай только так:

Да
Нет — <один короткий уточняющий вопрос>

Не пиши объяснений, рекомендаций, архитектурных идей и альтернатив, пока не пройден весь чек-лист.

1. Продуктовая рамка
   Понятно ли, что продукт — это single-screen migration console, а не workspace?
   Понятно ли, что основной сценарий только один: Yii AR → Doctrine XML?
   Понятно ли, что основной pipeline: scope → introspection → mapping candidates → blockers → validate → export?
   Понятно ли, что пользовательский результат — получить XML mapping candidates, blockers, custom relations и export bundle?
   Понятно ли, что решение должно работать без отдельных экранов graph/pattern lab/history?
2. Экран и layout
   Понятно ли, что маршрут один: /migration?
   Понятно ли, что слева должен быть Model Explorer в стиле инструментов БД в IDE?
   Понятно ли, что сверху должна быть панель действий: Run Introspection / Build Candidates / Validate / Export / Search?
   Понятно ли, что в центре должен быть registry с двумя режимами: Models и Relations?
   Понятно ли, что справа должен быть inspector выбранной модели или связи?
   Понятно ли, что снизу должен быть блок с Blockers / Validate / Export?
3. Левый explorer моделей
   Понятно ли, что список моделей слева — это не просто фильтр, а основной навигатор?
   Понятно ли, что каждая модель слева должна показываться как отдельный элемент списка/дерева?
   Понятно ли, что у модели слева должны быть:
   display name,
   FQCN,
   table,
   readiness,
   blockers count,
   custom relations count?
   Понятно ли, что у модели должно быть редактируемое имя отображения?
   Понятно ли, что у модели должна быть редактируемая заметка?
   Понятно ли, что у explorer должен быть switch вида?
   Понятно ли, какие именно виды explorer нужны?
   Понятно ли, нужен ли поиск по моделям прямо в левом explorer?
   Понятно ли, нужны ли фильтры по readiness / blockers / custom relations в левом explorer?
4. Подсветка связей
   Понятно ли, что подсветка связей — обязательная ключевая функция?
   Понятно ли, что при выборе модели ее связи должны подсвечиваться в списке моделей слева?
   Понятно ли, что при выборе модели связанные модели должны подсвечиваться в центральном registry?
   Понятно ли, что нужно различать:
   исходящие связи,
   входящие связи,
   custom relations,
   blocked relations,
   cyclic relations?
   Понятно ли, как именно показывать подсветку: цвет, лампочки, маркеры, полосы?
   Понятно ли, должна ли подсветка работать и по hover, и по click?
   Понятно ли, должна ли подсветка сбрасываться при смене выбора?
5. Scope
   Понятно ли, что scope можно задать через список FQCN?
   Понятно ли, что scope можно загрузить через scope.json?
   Понятно ли, что после загрузки scope нужно показать preview до запуска интроспекции?
   Понятно ли, что из scope можно удалить отдельные модели до запуска?
   Понятно ли, что scope должен копироваться обратно как JSON?
   Понятно ли, какие поля хранит migration_scope?
   Понятно ли, какие статусы должен иметь migration_scope?
6. Introspection
   Понятно ли, что интроспекция должна использовать:
   SQL metadata,
   Yii metadata,
   agent relation payload?
   Понятно ли, что результат интроспекции — это model_card[] и relation_card[]?
   Понятно ли, как маппить модель к таблице?
   Понятно ли, как извлекать relation hints из Yii metadata?
   Понятно ли, как объединять SQL/Yii/agent данные в один результат?
   Понятно ли, что при частичной неудаче интроспекции ошибки не скрываются, а попадают в blockers?
7. Models registry
   Понятно ли, какие колонки нужны в Models registry?
   Понятно ли, что строка модели должна открывать inspector?
   Понятно ли, должны ли строки моделей поддерживать сортировку?
   Понятно ли, должны ли строки моделей поддерживать фильтрацию?
   Понятно ли, нужно ли выделять связанную модель в таблице при выборе модели слева?
8. Relations registry
   Понятно ли, какие колонки нужны в Relations registry?
   Понятно ли, что relation должна иметь отдельную классификацию standard / custom / review / blocked?
   Понятно ли, что relation row должна открывать inspector?
   Понятно ли, что relation rows должны подсвечиваться при выборе модели?
   Понятно ли, как показывать owningSide?
   Понятно ли, как показывать joinInfo?
   Понятно ли, как показывать ошибки relation?
9. Inspector
   Понятно ли, что inspector работает и для модели, и для relation?
   Понятно ли, что у inspector есть только 4 режима:
   summary
   mapping
   xml
   diff?
   Понятно ли, что это переключение представлений одного объекта, а не навигация по экрану?
   Понятно ли, что в header inspector должны быть copy-actions?
   Понятно ли, что из inspector можно редактировать display name и note модели?
   Понятно ли, какие данные должны быть в summary для модели?
   Понятно ли, какие данные должны быть в mapping для модели?
   Понятно ли, что xml должен показывать XML preview в read-only виде?
   Понятно ли, как строить diff для модели?
   Понятно ли, какие данные должны быть в summary для relation?
   Понятно ли, какие данные должны быть в mapping для relation?
   Понятно ли, что xml для relation — это XML fragment, а не весь файл?
   Понятно ли, как строить diff для relation?
10. Candidate generation
    Понятно ли, что candidate generation должна строить:
    fields,
    ids,
    associations,
    owning side,
    XML preview,
    custom relation notes?
    Понятно ли, по каким правилам relation считается standard?
    Понятно ли, по каким правилам relation считается custom?
    Понятно ли, по каким правилам relation считается review?
    Понятно ли, по каким правилам relation считается blocked?
    Понятно ли, что custom relation — это relation, которую нельзя честно выразить обычным Doctrine mapping?
11. Blockers
    Понятно ли, что blockers — это отдельный рабочий список проблем?
    Понятно ли, какие колонки должны быть в blockers table?
    Понятно ли, что blocker должен быть связан с конкретной моделью или relation?
    Понятно ли, что blocker должен иметь:
    code,
    severity,
    message,
    source,
    fixability?
    Понятно ли, как отличать warning от blocking?
    Понятно ли, какие mismatch codes входят в стартовый словарь?
    Понятно ли, где blocker должен отображаться кроме нижней таблицы?
12. Validate
    Понятно ли, что validate — это не workflow approval, а проверка готовности?
    Понятно ли, какие пункты входят в validate checklist?
    Понятно ли, при каких условиях validate = pass?
    Понятно ли, при каких условиях validate = fail?
    Понятно ли, как validate summary должен отображаться в нижней панели?
13. Export
    Понятно ли, что export bundle должен включать:
    XML files,
    mapping summary JSON,
    blockers report MD,
    custom relations report MD,
    validate checklist MD?
    Понятно ли, когда bundle получает статус partial?
    Понятно ли, когда bundle получает статус ready?
    Понятно ли, где пользователь должен видеть список экспортируемых файлов?
    Понятно ли, какие действия доступны для каждого export entry?
14. Copy / Download
    Понятно ли, что в UI должен быть единый action cluster:
    Copy
    Copy JSON
    Copy XML или Copy SQL
    Download?
    Понятно ли, какие именно объекты обязаны копироваться?
    Понятно ли, в каких блоках action cluster обязателен?
15. Данные и контракты
    Понятно ли, какие поля входят в migration_scope?
    Понятно ли, какие поля входят в model_card?
    Понятно ли, какие поля входят в relation_card?
    Понятно ли, какие поля входят в mismatch?
    Понятно ли, какие поля входят в mapping_candidate?
    Понятно ли, какие поля входят в validation_report?
    Понятно ли, какие поля входят в export_bundle?
    Понятно ли, какие API нужны для:
    create/import scope,
    introspect,
    build candidates,
    validate,
    export,
    registry fetch,
    inspector fetch?
    Понятно ли, как backend должен хранить display name и note для модели?
    Формат финального ответа агента

Агент должен вернуть только это:

1. Да
2. Да
3. Нет — scope может существовать без sqlMetadataAttached или это обязательное условие?
4. Да
5. Нет — нужен ли отдельный визуальный код для cyclic relation в explorer, если relation уже blocked?