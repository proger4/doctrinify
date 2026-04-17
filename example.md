Отвечу по пунктам, чтобы убрать все неясности, а затем дам полностью переработанный, минималистичный и рабочий вариант.

### 🔍 Разбор ваших вопросов

#### 1. `WORKDIR /app` и как его комбинировать с образом раннера
Официальный образ `gitlab/gitlab-runner` не завязан на `WORKDIR`. Он читает конфиг строго из `/etc/gitlab-runner/config.toml` и запускает процессы от пользователя `gitlab-runner`.
Вы можете смело ставить `WORKDIR /app` — это **только** упростит вам `COPY` и задаст стартовую директорию, если вы зайдёте в контейнер через `docker exec`. На работу самого раннера это не повлияет.

#### 2. Зачем `RUN mkdir -p /etc/gitlab-runner /home/gitlab-runner`?
Эта строка **не нужна** для официального образа `gitlab/gitlab-runner:alpine...`. Он уже создаёт эти директории на этапе сборки. Её добавляют только когда берут голый `alpine:latest` или меняют `USER`. В вашем случае её можно убрать, чтобы не плодить лишний код.

#### 3. `exec gitlab-runner` vs команда регистрации
- `gitlab-runner register` — **утилита настройки**. Запускается один раз, генерирует `/etc/gitlab-runner/config.toml` и выходит.
- `gitlab-runner run` — **демон (процесс)**. Постоянно висит, опрашивает GitLab, забирает задачи, запускает их.
- `exec` — **заменяет текущий bash-процесс на процесс раннера**. В Docker это критически важно:
    - Раннер становится `PID 1` (получает сигналы `SIGTERM`/`SIGINT` от Docker при `docker compose down` или обновлении)
    - Логи пишутся напрямую в `stdout` без буферизации шеллом
    - Нет риска "зомби-процессов" и некорректного завершения

Без `exec` шелл остаётся `PID 1`, раннер работает как дочерний процесс, а Docker при остановке шлёт сигнал только шеллу, который может не передать его раннеру → контейнер зависает или обрывается жёстко.

---

### 📦 Обновлённая структура проекта
```
edge-runner/
├── Dockerfile
├── docker-compose.yml
├── register-and-run.sh
└── app/
    ├── requirements.txt
    └── scripts/
        └── edge_task.py
```
**Как это маппится внутрь контейнера:**
```
Хост: ./app/requirements.txt  → Контейнер: /app/requirements.txt
Хост: ./app/scripts/          → Контейнер: /app/scripts/
Хост: ./register-and-run.sh   → Контейнер: /usr/local/bin/register-and-run.sh
```

---

### 🐳 `Dockerfile`
```dockerfile
FROM gitlab/gitlab-runner:alpine3.21-v18.1.0

# Устанавливаем bash, python, pip и утилиты (Alpine не включает их по умолчанию)
RUN apk update && \
    apk add --no-cache bash python3 py3-pip git curl jq && \
    # Символические ссылки для удобства вызова в CI
    ln -sf python3 /usr/bin/python && \
    ln -sf pip3 /usr/bin/pip

# Ваша рабочая директория
WORKDIR /app

# Копируем зависимости и скрипты
COPY app/requirements.txt ./
COPY app/scripts/ ./scripts/

# Устанавливаем Python-зависимости
RUN pip3 install --no-cache-dir -r requirements.txt

# Скрипт запуска
COPY register-and-run.sh /usr/local/bin/
RUN chmod +x /usr/local/bin/register-and-run.sh

# EntryPoint переопределён в register-and-run.sh
ENTRYPOINT ["/usr/local/bin/register-and-run.sh"]
```

---

### 📜 `register-and-run.sh`
```bash
#!/bin/bash
set -e

CONFIG_FILE="/etc/gitlab-runner/config.toml"

# Регистрация только если конфиг отсутствует или в нём нет токена
if [ ! -f "$CONFIG_FILE" ] || ! grep -q 'token\s*=' "$CONFIG_FILE"; then
    echo "🔄 Registering runner..."
    gitlab-runner register --non-interactive \
        --url "$CI_SERVER_URL" \
        --token "$RUNNER_TOKEN" \
        --executor "shell" \
        --name "${RUNNER_NAME:-edge-agent}" \
        --tag-list "${RUNNER_TAG_LIST:-edge,python}" \
        --shell "bash" \
        --run-untagged="true" \
        --locked="false"
    echo "✅ Registration complete."
else
    echo "⏭️  Runner already registered. Skipping registration."
fi

# Запускаем раннер как PID 1 (exec заменяет текущий bash)
echo "🚀 Starting GitLab Runner daemon..."
exec gitlab-runner run --config "$CONFIG_FILE"
```

---

### 🐙 `docker-compose.yml`
```yaml
services:
  edge-runner:
    build: .
    container_name: gitlab-edge-runner
    restart: unless-stopped
    environment:
      - CI_SERVER_URL=https://gitlab.yourcompany.com
      - RUNNER_TOKEN=glrt-xxxxxxxxxxxxxxxxxxxx  # Auth-токен из UI GitLab
      - RUNNER_NAME=edge-agent-01
      - RUNNER_TAG_LIST=edge,python
      # Прокси (если нужен доступ в интернет через корпоративный прокси)
      # - HTTP_PROXY=http://proxy.internal:3128
      # - HTTPS_PROXY=http://proxy.internal:3128
      # - NO_PROXY=localhost,127.0.0.1,*.internal
    volumes:
      - runner-config:/etc/gitlab-runner  # Сохраняет config.toml между рестартами
    networks:
      - edge-net
    dns:
      - 8.8.8.8
      - 1.1.1.1

volumes:
  runner-config:

networks:
  edge-net:
    driver: bridge
```

---

### 🧠 Как это работает на практике

1. **Первый запуск:**
    - Скрипт видит, что `/etc/gitlab-runner/config.toml` нет → вызывает `gitlab-runner register`
    - Записывает конфиг в volume `runner-config`
    - `exec` запускает демон раннера

2. **Последующие запуски / `docker compose restart`:**
    - Конфиг уже есть → регистрация пропускается
    - Сразу запускается `gitlab-runner run`

3. **Доступ в интернет:**
    - Docker bridge по умолчанию даёт outbound-доступ. Если хост за NAT/прокси → передаёте переменные окружения `HTTP_PROXY`/`HTTPS_PROXY`. Раннер и Python-скрипты подхватят их автоматически.

4. **Проблемы с шеллом и командами:**
    - Мы явно установили `bash` и передали `--shell "bash"`
    - В `.gitlab-ci.yml` используйте **абсолютные пути**, т.к. `shell`-исполнитель создаёт временную рабочую директорию для каждого job'а:
      ```yaml
      job:
        tags: [edge, python]
        script:
          - python /app/scripts/edge_task.py
      ```

---

### ⚙️ Быстрый старт
```bash
# 1. Создайте структуру и файлы выше
mkdir -p edge-runner/app/scripts
# ... положите файлы ...

# 2. Соберите и запустите
cd edge-runner
docker compose up -d --build

# 3. Смотрите логи
docker compose logs -f
```

### ❗ Важные нюансы
| Проблема | Решение |
|----------|---------|
| `bash: command not found` | Убран. `apk add bash` + `--shell "bash"` фиксирует это |
| Раннер теряет регистрацию после рестарта | Volume `runner-config` сохраняет `config.toml` |
| `exec` vs просто `gitlab-runner run` | Без `exec` контейнер не корректно останавливается, логи буферизуются, PID 1 = bash |
| Python не видит пакеты в CI | `pip install` происходит на этапе `docker build`, пакеты уже в образе |
| GitLab 17+ требует новый токен | В UI создайте Runner → скопируйте `Authentication Token` (`glrt-...`) → вставьте в `RUNNER_TOKEN` |

Если нужно добавить healthcheck, автоматическое обновление зависимостей или настроить `docker`-exec вместо `shell` — напишите, адаптирую под ваш use-case.