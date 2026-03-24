# Задача: introspection-core.md

## Цель
Собрать минимальный рабочий цикл attach metadata + introspection.

## Входит в задачу
- `Attach Metadata Dialog` с тремя секциями: SQL, Yii, agent payload.
- API attach metadata и обновление attached badges.
- Запуск `Run Introspection` и отображение progress.
- Результат интроспекции: `model_card[]`, `relation_card[]`, `mismatches[]`.
- При частичной неудаче ошибки не теряются и попадают в blockers.

## Не входит (до решений)
- Формальный приоритет SQL/Yii/agent при конфликтном merge.
- Детальные правила извлечения relation hints.

## Результат
- После интроспекции интерфейс получает модели/связи/расхождения и может продолжать pipeline.
