# Задача: relation-highlighting.md

## Цель
Реализовать обязательную подсветку связей между моделями.

## Входит в задачу
- Алгоритм relation highlight для выбранной модели:
  - `outgoing`, `incoming`, `both`, `custom`, `blocking`, `none`
- Приоритет состояния: `blocking > custom > both > outgoing > incoming > none`.
- Визуализация: цвет левой полосы + правые лампочки `R/C/B` + optional tint.
- Подсветка по `click` и временный preview по `hover`.
- Сброс подсветки при смене selected модели.
- Поведение `Focus relations`: dimmed для несвязанных строк.

## Не входит (до решений)
- Отдельный код подсветки для `cyclic relation`.

## Результат
- Пользователь видит topology/custom/blocking состояние связей напрямую в левом списке.
