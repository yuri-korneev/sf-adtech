# SF‑AdTech — трекер трафика (Laravel 12, PHP 8.3)

Учебный проект для демонстрации работы **трекера трафика** между рекламодателями (**Advertiser**) и веб‑мастерами (**Webmaster**). 
Проект реализует офферы с CPC, подписки с уникальными токенами, публичный редиректор `/r/{token}`, логирование кликов (асинхронно через очереди), статистику (день/месяц/год) и CSV‑экспорты.

## Быстрый обзор возможностей

- **Webmaster**
  - Просмотр активных офферов и **подписка** (получает персональный `token`).
  - Список своих подписок и ссылка вида `/r/{token}`.
  - **Статистика дохода** и кликов в разрезе *day / month / year*, **CSV**.

- **Advertiser**
  - **CRUD офферов**: название, **CPC**, `target_url`, темы, (де)активация.
  - Просмотр числа подписчиков (WM) по каждому офферу.
  - **Статистика расходов** по своим офферам (day/month/year), **CSV**.

- **Admin**
  - Пользователи (роль, активность), офферы, подписки.
  - Клики (валид/невалид, «отказы»), **доход системы**.
  - CSV‑выгрузки, фильтры, быстрые счётчики на дашборде.

- **Редиректор** `/r/{token}`
  - Проверяет валидность подписки и оффера, при ошибке — **404**.
  - Логирует клик **асинхронно** (очереди), **сохраняет query‑строку**.
  - Мгновенно делает `302` на `target_url` оффера.

- **Канбан**
  - Перетаскивание карточек офферов мышью (drag-and-drop) 

---

## Установка и запуск

1) Клонируйте проект и зайдите в папку `sf-adtech`  
2) Установите PHP‑зависимости:
```bash
composer install
```
3) Создайте `.env` и сгенерируйте ключ:
```bash
cp .env.example .env
php artisan key:generate
```
4) Настройте БД в `.env` (MySQL 8.x) и очередь:
```
QUEUE_CONNECTION=database
```
5) Примените миграции и сидеры:
```bash
php artisan migrate --seed
```
6) Запустите обработчик очередей (нужен для логирования кликов):
```bash
php artisan queue:work
```
7) Откройте сайт: `http://localhost` (или ваш `APP_URL`).

**Готовые пользователи (сидер):**
- Admin: `admin@example.com` / `password`
- Advertiser: `adv@example.com` / `password`
- Webmaster: `wm@example.com` / `password`

---

## Где что находится (карта проекта)

- **Маршруты:** `routes/web.php`
  - `/r/{token}` → `App\Http\Controllers\RedirectController`
  - Группы `/wm`, `/adv`, `/admin` (доступ через middleware ролей)
- **Роли и доступ:** `app/Http/Middleware/RoleMiddleware.php` (проверка `user.role`, `is_active`)
- **Редиректор и очереди:**
  - Контроллер: `app/Http/Controllers/RedirectController.php`
  - Джобы: `app/Jobs/LogClickJob.php`, `app/Jobs/MarkRedirectedJob.php`
  - Очереди: драйвер `database` (`jobs`), запуск `php artisan queue:work`
- **Модели:** `app/Models/Offer.php`, `Subscription.php`, `Click.php`, `Topic.php`, `User.php`
- **Статистика и финансы:** `app/Services/StatsService.php` (+ `Wm/StatsController.php`, `Adv/OfferController@stats`, `Admin/AdminController@revenueStats`)
- **Представления (Blade):** `resources/views/{wm,adv,admin}/*.blade.php`
- **Конфиг проекта:** `config/sf.php` (комиссия, окно дедупликации, лимиты, дефолтные периоды отчетов)

---

## Бизнес‑логика 

- **CPC и комиссия**: рекламодатель задаёт `offers.cpc`. Комиссия системы в `config/sf.php` (`SYSTEM_COMMISSION` в `.env`).  
  Формулы:  
  `wm_payout = cpc * (1 - commission)`, `system_revenue = cpc * commission`.
- **Темы оффера**: справочник `topics` и связь `offer_topic`.
- **Подписка WM**: при подписке создаётся/активируется запись `subscriptions` и генерируется `token` (ULID).  
  Ссылку `/r/{token}` WM размещает у себя.
- **Редиректор**: проверяет связку `token → subscription → offer`. Если всё ок — записывает задачу логирования клика и **сразу** редиректит на `target_url` оффера, добавляя исходную `?query` если была.
- **Статистика**: странички для WM/Adv/Admin показывают клики и деньги в разрезе **день/месяц/год**; есть **CSV**.

---

## Полезные команды

- Сгенерировать тестовые клики:
```bash
php artisan clicks:generate 200 --days=14 --refused=25
```
- Запустить обработчик очередей:
```bash
php artisan queue:work
```

---

## Линтеры и автоформатирование

Единообразный стиль кода ускоряет ревью и снижает шанс багов.

### PHP (PHP_CodeSniffer: phpcs/phpcbf, профиль PSR-12)
- Конфиг: `phpcs.xml` (в корне проекта).
- Проверка:
  ```bash
  composer exec phpcs -- app routes
  ```
- Автоисправление (где возможно):
  ```bash
  composer exec phpcbf -- app routes
  ```

#### Рекомендованные записи в `composer.json`
```json
{
  "scripts": {
    "lint:php": "phpcs -- app routes",
    "fix:php": "phpcbf -- app routes"
  }
}
```
Тогда:
```bash
composer run lint:php
composer run fix:php
```

### JavaScript (ESLint)
- Конфиги: `.eslintrc.json` и/или `eslint.config.mjs`.
- Проверка:
  ```bash
  npx eslint resources/js
  ```
- Автопочинка:
  ```bash
  npx eslint resources/js --fix
  ```


### Дополнительно
- **.editorconfig** — одинаковые отступы, кодировка, конечные пробелы.
- **.gitattributes** — нормализация перевода строк (LF) и safelist бинарников.

---

## Безопасность и производительность

- Eloquent/Query Builder (защита от SQL‑инъекций).
- CSRF на формах, Blade экранирует HTML (XSS).
- Ограничение частоты для редиректора (RateLimiter), конфиг в `config/sf.php`.
- Логирование кликов вынесено в **очереди** — редирект быстрый.

---

## Как быстро проверить вручную

1. Зайдите под **Advertiser**, создайте оффер (CPC, `target_url`, темы, активируйте).
2. Зайдите под **Webmaster**, подпишитесь на оффер и возьмите ссылку `/r/{token}`.
3. Откройте ссылку в браузере с любым `?utm=...` — вас должно перенаправить на `target_url` с сохранением query.
4. Убедитесь, что запущен `queue:work`. Откройте **статистику** у WM и у Adv — клики и суммы появятся.
5. В **Admin** проверьте клики, «отказы» и «доход системы».

---

## Лицензия

Учебный проект. Используется Laravel и стандартные компоненты. 
