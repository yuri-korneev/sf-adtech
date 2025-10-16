# SF-AdTech — учебный трекер трафика на Laravel

Многорольное приложение (**Admin**, **Advertiser/Adv**, **Webmaster/WM**) для учёта переходов по партнёрским ссылкам, с редиректором `/r/{token}`, ролями и правами, статистикой по дням/месяцам/годам, экспортами CSV, очередями для логирования кликов и пометки редиректов. 

> Технологии: **Laravel 12**, **PHP 8.3**, **MySQL 8.x**, **Blade + Tailwind**, очереди **database**.

---

## Содержание

- [Демо-возможности по ролям](#демо-возможности-по-ролям)
- [Архитектура и ключевые модули](#архитектура-и-ключевые-модули)
- [Быстрый старт (локально / Homestead)](#быстрый-старт-локально--homestead)
- [Конфигурация `.env`](#конфигурация-env)
- [Структура БД и сиды](#структура-бд-и-сиды)
- [Редиректор `/r/{token}`: как работает](#редиректор-rtoken-как-работает)
- [Статистика и экспорт CSV](#статистика-и-экспорт-csv)
- [Безопасность](#безопасность)
- [Кодстайл и утилиты](#кодстайл-и-утилиты)
- [Чек-лист соответствия ТЗ](#чек-лист-соответствия-тз)
- [Лицензия](#лицензия)

---

## Демо-возможности по ролям

### Admin (`/admin`)
- Управление пользователями (активация/деактивация).
- Список офферов, темы (topics), подписки (выданные ссылки).
- Сводка кликов и **разрезов** по дням/месяцам/годам, **CSV-экспорт**.
- Контроль отказов (клик с неподписанного WM → 404).

### Advertiser (`/adv`)
- **Офферы (CRUD)**: имя, CPC, целевой URL, список тем (many-to-many), активность, статус.
- **Подписчики** (веб-мастера) по каждому офферу.
- Статистика расходов и кликов по дням/месяцам/годам, **CSV-экспорт**.
- **Kanban**-представление статусов офферов.

### Webmaster (`/wm`)
- Список собственных подписок (офферы, на которые подписан).
- **Подписаться** на оффер (зафиксировать свою ставку), получить **персональную ссылку**.
- **Отписаться** от оффера.
- Доходы и клики по дням/месяцам/годам, **CSV-экспорт**.

**Демо-аккаунты для входа:**

| Роль        | Email                  | Пароль   |
|-------------|-------------------------|----------|
| Admin       | admin@example.com       | secret123 |
| Advertiser  | adv1@example.com         | secret123 |
| Webmaster   | wm1@example.com          | secret123 |
и т.д.

---

## Архитектура и ключевые модули

- **MVC (Laravel)**
  - **Модели:** `User`, `Offer`, `Topic`, `Subscription`, `Click`.
  - **Контроллеры:**
    - `Admin\AdminController`, `Admin\TopicController`
    - `Adv\DashboardController`, `Adv\OfferController`
    - `Wm\DashboardController`, `Wm\SubscriptionController`, `Wm\StatsController`
    - `RedirectController` — публичный редиректор `/r/{token}`.
- **Очереди / Jobs**
  - `LogClickJob` — асинхронно логирует клик (ip, ua, referer/referrer, token).
  - `MarkRedirectedJob` — идемпотентно проставляет `redirected_at` с учётом окна дедупликации.
- **Конфиг проекта:** `config/sf.php`
  - `commission` / `system_commission`
  - `dedup_window_seconds` (по умолчанию 600 сек)
  - лимиты статистики (период, max_days).
- **Маршруты:** `routes/web.php`
  - Группы для `admin`, `adv`, `wm` с middleware `auth` + `role:*`.
  - Rate limiting для редиректора: `throttle:redirects` (на IP).
- **Вёрстка:** Blade + Tailwind.

---

## Быстрый старт (локально / Homestead)

### 1) Клонирование и зависимости
```bash
git clone <repo-url> sf-adtech
cd sf-adtech
composer install
npm install
```

### 2) Конфиг `.env`
```bash
cp .env.example .env
php artisan key:generate
```

Заполните секцию БД (MySQL 8.x) и URL проекта, например:
```dotenv
APP_URL=http://sf-adtech.test
```

Для очередей:
```dotenv
QUEUE_CONNECTION=database
```

**Homestead (рекомендуется):**
- Добавьте сайт `sf-adtech.test` в `Homestead.yaml`, пробросьте папку проекта.
- Выполняйте `php artisan` **изнутри** VM (`vagrant ssh`).

### 3) Миграции, сиды, очереди, ассеты
```bash
php artisan migrate --seed
php artisan queue:table && php artisan migrate   # если не создана таблица jobs
php artisan queue:work                           # запустить воркер для кликов/редиректов
npm run dev                                      # ассеты (во время разработки)
```

---

## Конфигурация `.env`

Минимально важно:
```dotenv
APP_ENV=local
APP_DEBUG=true
APP_URL=http://sf-adtech.test

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=sfadtech
DB_USERNAME=homestead
DB_PASSWORD=secret

QUEUE_CONNECTION=database
SESSION_DRIVER=database
```

Параметры из `config/sf.php` можно вынести в `.env`:
```dotenv
SYSTEM_COMMISSION=0.20
DEDUP_WINDOW_SECONDS=600
STATS_DEFAULT_PERIOD=7d
STATS_MAX_DAYS=365
```

---

## Структура БД и сиды

**Основные таблицы:**
- `users` — роли `role` (`admin|adv|wm`), активность `is_active`.
- `offers` — `advertiser_id`, `name`, `cpc`, `target_url`, `is_active`, `status`.
- `topics` и `offer_topic` — связь офферов и тем (many-to-many).
- `subscriptions` — `wm_id`, `offer_id`, `token`, `wm_cpc`, `is_active`, timestamps.
- `clicks` — `subscription_id`, `token`, `ip`, `ua`, `referer`, `clicked_at`, `redirected_at`, `is_valid`, `invalid_reason`.

**Сиды:**
- `RoleAndUserSeeder` — три пользователя (admin/adv/wm), роли и активность.
- `TopicsSeeder` — базовый набор тем.
- (Опционально) сиды кликов для учебных графиков/отчётов.

> SQL-дамп лежит в `database/dumps/…sql`.

---

## Редиректор `/r/{token}`: как работает

1. **Валидация подписки и оффера**: токен принадлежит активной подписке WM на активный оффер с `target_url`. При несоответствии возвращается `404` и записывается причина в `clicks`.
2. **Построение финального URL**: сохраняется исходная query-строка пользователя.
3. **Асинхронное логирование** (`LogClickJob`): IP, UA, referer/referrer, token. Ошибки логирования не влияют на UX (best-effort).
4. **Идемпотентная пометка `redirected_at`**:
   - используется окно «свежести» из `config('sf.dedup_window_seconds')` (дефолт 600 секунд);
   - помечается наиболее свежий неотмеченный клик; при гонках используется `MarkRedirectedJob`.
5. **Редирект**: `302` на целевой URL.
6. **Защита**: `RateLimiter` для `/r/{token}` (на IP).

---

## Статистика и экспорт CSV

- Отчёты по дням / месяцам / годам с выбором диапазона (ограничение глубины предусмотрено).
- Табличные представления и экспорт CSV во всех ролях.
- В админ-отчёте метрики revenue учитывают `commission` из конфигурации.

---

## Безопасность

- CSRF — маркеры во всех формах, стандартная защита Laravel.
- XSS — экранирование шаблонов Blade по умолчанию.
- SQL-инъекции — запросы через Eloquent/Query Builder.
- Роли/доступ — middleware `auth` + `role:*`.
- Rate limiting — для публичного маршрута редиректора.
- Сессии — `SESSION_DRIVER=database`.

---

## Кодстайл и утилиты

- PHP — PSR-12 (`phpcs`), автофиксы `phpcbf`.
- JS — ESLint + Prettier.
- Полезные команды:
  ```bash
  composer run lint:php
  composer run lint:js
  composer test

  php artisan route:list
  php artisan tinker
  php artisan queue:work
  ```
- При необходимости:
  ```bash
  php artisan session:table && php artisan migrate
  ```

---

## Чек-лист соответствия ТЗ

- Наличие трёх ролей: Admin, Advertiser, Webmaster, разграничение доступа по ролям.
- Реализация публичного редиректора `/r/{token}` с валидацией подписки/оффера, логированием кликов, пометкой `redirected_at`, обработкой 404 и 302-редиректом.
- Возможность подписки/отписки вебмастера на офферы и получение персональной ссылки (токена).
- Наличие отчётов по дням/месяцам/годам и экспорта CSV в разделах всех ролей.
- Учёт комиссии системы в админ-отчёте, конфигурирование комиссии через `config/sf.php`/`.env`.
- Использование очередей для асинхронного логирования кликов и пометки редиректов.
- Структура БД через миграции, наличие сидов для стартовых ролей/пользователей/тем.
- Базовые меры безопасности (CSRF, XSS, защита от SQL-инъекций), rate limiting редиректора.
- Наличие git-репозитория и инструкций по разворачиванию проекта.

---

## Лицензия

Свободно для учебного использования.  
Автор: **Iurii Korneev**.
