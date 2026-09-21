# Custom Search for Drupal 10

Drupal 10 module that adds a footer search with live suggestions: type a query → a dropdown suggests pages, press **Enter** → the full results page opens.

- **Machine name:** `custom_search`
- **Composer package:** `mmitekk/drupal-custom-search`
- **Type:** `drupal-module`
- **Core:** Drupal `^10` (tested on 10.6.x, incl. 10.6.8), PHP `>= 8.1`
- **Stable releases only:** install via tagged GitHub Releases (e.g. `^1.0`), no `dev-main` by default.

> 🇷🇺 Русская инструкция — ниже. 🇬🇧 English instructions — below.
> Описание модуля на русском и английском также разнесено по табам на странице настроек: `/admin/config/search/custom-search` → табы **«Описание (RU)»** / **«Description (EN)»**.

---

## 🇷🇺 Описание (RU)

<details open>
<summary><strong>Показать / скрыть инструкцию на русском</strong></summary>

### Что делает модуль

- Блок **«Custom Search»** для подвала сайта.
- При вводе запроса под полем появляются **подсказки**: название + короткое описание + категория (`Услуги / Врачи / Разделы / Инфо`), совпадения подсвечены `<mark>`.
- Навигация: `↑` / `↓` — выбор, `Enter` — открыть подсказку или страницу выдачи, `Esc` — закрыть, `Ctrl/⌘ + K` — фокус на поиске, клик мимо — закрыть.
- Страница выдачи **`/searching?q=…`**: форма поиска + список результатов + пагинация + подсветка совпадений.
- Источник — опубликованные ноды (`title` + `body`), с фильтром по типам материалов и лимитами.
- Задержка запросов **180 мс** (debounce) + отмена предыдущего запроса (`AbortController`).

### Требования

- Drupal 10 (`^10`, проверено на 10.6.8)
- PHP >= 8.1
- Права: `access content` (поиск), `administer custom search` (настройки)

### Установка через Composer (стабильный релиз)

Модуль ставится **только тегированными релизами** (например `1.0.0`), `dev`-версии по умолчанию не нужны.

**Вариант A — Packagist (если пакет опубликован):**

```bash
composer require mmitekk/drupal-custom-search:^1.0
```

**Вариант B — напрямую с GitHub (VCS, рекомендуется до публикации на Packagist):**

```bash
# 1. Добавить репозиторий (один раз на проект):
composer config repositories.drupal-custom-search vcs https://github.com/Mmitekk/drupal-custom-search

# 2. Поставить стабильный релиз:
composer require mmitekk/drupal-custom-search:^1.0

# 3. Включить модуль:
php web/core/scripts/drupal install:module 2>/dev/null || drush en custom_search -y
# или без drush:
# php -r "require 'web/autoload.php';"  # затем включите модуль в /admin/modules
```

> Почему ставится **сразу стабильно**, а не `dev`:
>
> - В `composer.json` стоит `"minimum-stability": "stable"` + `"prefer-stable": true`.
> - Релизы GitHub = git-теги вида `1.0.0` (без префикса `dev`).
> - Constraint `^1.0` резолвится только в стабильные теги. `dev-main` поставится только если явно попросить `dev-main`.

Проверка версии:

```bash
composer show mmitekk/drupal-custom-search
```

Должно показать `versions: 1.0.0` (или новее), **не** `dev-main`.

### Включение и размещение блока

1. `/admin/modules` → включите **Custom Search**.
2. `/admin/structure/block` → разместите блок **«Custom Search»** в регион **Footer** (или любой нижний регион темы).
3. Откройте сайт, проскролльте в подвал — там поле поиска.

### Если модуля нет в списке `/admin/modules`

1. Проверьте, куда Composer положил пакет (начиная с 1.0.1 — всегда `web/modules/contrib/custom_search`):
   ```bash
   ls web/modules/contrib/ | grep -i custom
   cat web/modules/contrib/custom_search/custom_search.info.yml
   ```
2. Если осталась старая папка `web/modules/contrib/drupal-custom-search` — удалите её после обновления до 1.0.1+:
   ```bash
   composer update mmitekk/drupal-custom-search
   rm -rf web/modules/contrib/drupal-custom-search
   ```
3. Перестройте кэш (`drush cr` или «Конфигурация → Производительность → Очистить все кэши») и ищите в списке по слову `Custom`.
4. Ошибка вида `Could not delete .../default.settings.php` при `composer require` — это права на scaffold-файл, на установку модуля она не влияет.

### Настройки: `/admin/config/search/custom-search`

- Таб **«Main settings»**: плейсхолдер, мин. длина запроса, лимиты подсказок/выдачи, типы материалов, показ метки типа.
- Таб **«Описание (RU)»**: описание + заголовок выдачи (RU) + текст «ничего не найдено» (RU, `@q` = запрос).
- Таб **«Description (EN)»**: то же на английском.

### Использование

- Ввод в поле → подсказки (`/searching/suggest?q=…` возвращает JSON `{results: [{type,title,description,path}]}`).
- `Enter` → `/searching?q=…`.
- Типы подсказок: `service` (услуги), `doctor` (врачи), `page` (разделы), `info` (статьи/FAQ). Маппинг по machine name типа ноды (настраивается кодом в `SuggestController::mapType()`).
- В настройках блока есть опция «Закрепить внизу экрана» (по умолчанию включена): панель поиска висит снизу на всех страницах.
- Проверка подсказок: откройте в браузере `/searching/suggest?q=хомут` — должен вернуться JSON.

### Обновление на новый релиз

```bash
composer update mmitekk/drupal-custom-search
drush updb -y && drush cr
```

Откат на конкретный релиз:

```bash
composer require mmitekk/drupal-custom-search:1.0.0
```

</details>

---

## 🇬🇧 Description (EN)

<details>
<summary><strong>Show / hide English instructions</strong></summary>

### What it does

- A **“Custom Search”** block for the site footer.
- While typing, a dropdown suggests pages: title + snippet + category (`Services / Doctors / Pages / Info`), matches highlighted with `<mark>`.
- Keyboard: `↑`/`↓` to pick, `Enter` opens the suggestion or the results page, `Esc` closes, `Ctrl/⌘ + K` focuses search, click-outside closes.
- Results page **`/searching?q=…`**: search form + results + pager + highlighting.
- Source: published nodes (`title` + `body`), filterable by bundles, with limits.
- **180 ms** debounce + previous-request cancel (`AbortController`).

### Requirements

- Drupal 10 (`^10`, verified on 10.6.8)
- PHP >= 8.1
- Permissions: `access content` (search), `administer custom search` (settings)

### Install via Composer (stable release)

The module is installed from **tagged releases** (e.g. `1.0.0`); no `dev` version by default.

**Option A — Packagist (once published):**

```bash
composer require mmitekk/drupal-custom-search:^1.0
```

**Option B — directly from GitHub (VCS, recommended before Packagist):**

```bash
# 1. Add the repository (once per project):
composer config repositories.drupal-custom-search vcs https://github.com/Mmitekk/drupal-custom-search

# 2. Require a stable release:
composer require mmitekk/drupal-custom-search:^1.0

# 3. Enable:
drush en custom_search -y
# or enable at /admin/modules
```

> Why it installs **stable right away** instead of `dev`:
>
> - `composer.json` sets `"minimum-stability": "stable"` + `"prefer-stable": true`.
> - GitHub Releases are git tags like `1.0.0`.
> - The `^1.0` constraint resolves only to stable tags. `dev-main` is installed only on explicit request.

Verify:

```bash
composer show mmitekk/drupal-custom-search
```

It should report `versions: 1.0.0` (or newer), **not** `dev-main`.

### Enable & place the block

1. `/admin/modules` → enable **Custom Search**.
2. `/admin/structure/block` → place **“Custom Search”** into **Footer**.
3. Scroll to the footer — the search field is there.

### If the module is missing from `/admin/modules`

1. Check where Composer put the package (since 1.0.1 — always `web/modules/contrib/custom_search`):
   ```bash
   ls web/modules/contrib/ | grep -i custom
   cat web/modules/contrib/custom_search/custom_search.info.yml
   ```
2. If a stale `web/modules/contrib/drupal-custom-search` folder remains — remove it after updating to 1.0.1+:
   ```bash
   composer update mmitekk/drupal-custom-search
   rm -rf web/modules/contrib/drupal-custom-search
   ```
3. Rebuild the cache (`drush cr` or “Configuration → Performance → Clear all caches”) and search the list for `Custom`.

### Settings: `/admin/config/search/custom-search`

- **Main settings** tab: placeholder, min length, suggestion/results limits, bundles, type label.
- **Описание (RU)** tab: Russian description + results header + “nothing found” text (`@q` = query).
- **Description (EN)** tab: the same in English.

### Usage

- Typing queries `/searching/suggest?q=…` → JSON `{results: [{type,title,description,path}]}`.
- `Enter` → `/searching?q=…`.
- Suggestion types: `service`, `doctor`, `page`, `info` (mapped from node bundle in `SuggestController::mapType()`).
- The block has a “Pin to the bottom of the screen” option (on by default): a sticky search bar on every page.
- Suggestions check: open `/searching/suggest?q=test` in the browser — JSON is expected.

### Update

```bash
composer update mmitekk/drupal-custom-search
drush updb -y && drush cr
```

Pin a version:

```bash
composer require mmitekk/drupal-custom-search:1.0.0
```

</details>

---

## API

- `GET /searching/suggest?q=...` → `{"results": [{"type": "service|doctor|page|info", "title": "...", "description": "...", "path": "/node/1"}]}` (permission: `access content`)
- `GET /searching?q=...` → HTML results page with pager (permission: `access content`)
- `/search/node?keys=...` (core search) → 301 redirect to `/searching?q=...`

## Uninstall

1. Remove the block from `/admin/structure/block`.
2. `/admin/modules/uninstall` → uninstall **Custom Search**.
3. `composer remove mmitekk/drupal-custom-search`

## License

GPL-2.0-or-later. See `LICENSE`.
