# Открытые вопросы (пункты `Нет`)

1. Нужны ли в левом explorer отдельные фильтры readiness/blockers/custom relations помимо поиска и Focus relations?
2. Нужен ли отдельный визуальный код для cyclic relation, если уже есть состояния both/blocking?
3. Нужно ли явное действие `Copy scope JSON` в UI, и где именно оно должно находиться?
4. Какой приоритет источников (SQL/Yii/agent) использовать при конфликте маппинга модели к таблице?
5. Какие обязательные правила извлечения relation hints из Yii metadata входят в v1?
6. Какая стратегия merge SQL/Yii/agent при конфликтующих relation-данных?
7. Сортировка в Models registry нужна по всем колонкам или по ограниченному набору?
8. Какие фильтры в Models registry обязательны в минимальном v1?
9. Подсветка связанной модели при выборе слева должна быть в Models registry или только в Relations registry?
10. JoinInfo показываем отдельной колонкой в Relations registry или только в inspector?
11. Редактирование display name и note из inspector должно быть inline или через popover?
12. По каким условиям relation получает classification `review` в v1?
13. Validate=`pass` считаем только при отсутствии blocking mismatches или требуется полный pass всех пунктов checklist?
14. Какие точные условия присвоения export bundle статуса `partial`?
15. Какие точные условия присвоения export bundle статуса `ready`?
16. Нужен единый action cluster одинакового состава для всех блоков или состав может отличаться по контексту?
17. В каких конкретно блоках action cluster обязателен в v1 (минимальный список)?
18. Нужны отдельные API для registry fetch/inspector fetch или достаточно данных из текущих session endpoints?
19. Где backend хранит display name/note: в `scope.annotations`, в отдельной таблице, или в обоих местах?
