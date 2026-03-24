# Задача: layout.md (первая в работу)

## Цель
Собрать каркас единственного экрана `/migration` в IDE-подобной компоновке с 5 зонами и resizable panel behavior.

## Входит в задачу
- Роут `/migration` как единственная рабочая страница инструмента.
- 5 зон экрана:
  - `Left Navigator`
  - `Top Action Bar`
  - `Center Workspace`
  - `Right Inspector`
  - `Bottom Workbench`
- Базовые размеры/ограничения из ТЗ:
  - top bar `56px` fixed
  - left pane `320px`, resizable `280-420px`
  - right pane `420px`, resizable `360-560px`, закрываемый
  - bottom pane `240px`, collapsible, expanded до `420px`
- Подключение resizable layout библиотеки и состояние открытия/закрытия правой и нижней панели.
- Плейсхолдер-компоненты для всех 5 зон с корректной раскладкой и скроллами.

## Не входит (до решений)
- Точные визуальные коды cyclic relation.
- Контент и бизнес-логика внутренних таблиц/инспектора.

## Результат
- Рабочий UI-shell `/migration`, на который можно параллельно навешивать функциональные фичи.
