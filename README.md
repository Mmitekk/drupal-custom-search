# Custom Search for Drupal 9 / 10 / 11

Drupal 9/10/11 module that adds a footer search with live suggestions: type a query → a dropdown suggests pages, press **Enter** → the full results page opens.

- **Machine name:** `custom_search`
- **Composer package:** `mmitekk/drupal-custom-search`
- **Type:** `drupal-module`
- **Core:** Drupal `^9.2 || ^10 || ^11` (tested on 10.6.x, incl. 10.6.8), PHP `>= 7.4`
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

- Drupal `^9.2 || ^10 || ^11`
  - Drupal 9: версии **9.2–9.5** (9.0 и 9.1 не поддерживаются — они давно вне поддержки и в них нет библиотеки `core/once`, на которой построен модуль).
  - Drupal 10: все актуальные версии (проверено на 10.6.8).
  - Drupal 11: все актуальные версии.
- PHP >= 7.4 (Drupal 9 рассчитан на PHP 7.3–8.1, Drupal 10 — на PHP 8.1+, Drupal 11 — на PHP 8.3+)
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

### Страница выдачи и тема сайта

Страница `/searching` — обычный маршрут Drupal, который отдаёт render array, поэтому она **всегда рендерится внутри основного шаблона темы** (`html.html.twig` → `page.html.twig`) со всеми регионами и блоками. Отдельного «голого» шаблона у модуля нет; suggestion `page__searching` ядро подставляет автоматически.

Если на выдаче пропали шапка/меню/подвал, а `page--searching.html.twig` из `page.html.twig` ничего не изменил — значит, в вашей теме обвязка сайта **не в регионах** (регионы `page.header`/`page.footer` пустые на всех страницах, это видно и на 404), а зашита в **node-шаблоне** (`node--page.html.twig` / `node.html.twig` в `templates/` темы — признак: внутри есть разметка `.page-header`). Модуль не может подтянуть её сам: node-шаблон применяется только к нодам, а страница выдачи — не нода.

Рецепт (порядок важен):

1. Откройте `templates/node--page.html.twig` вашей темы (или `node.html.twig` — файл, где есть `.page-header`).
2. Создайте `templates/page--searching.html.twig` как копию этого файла, но вместо внутренностей ноды (`{{ content }}`, label и пр.) выведите `{{ page.content }}`. Шапка — всё до контента, подвал — всё после.
3. Очистите кэш (`drush cr` или Конфигурация → Производительность → «Очистить все кэши») — без этого twig не увидит новый файл.
4. Проверка, что файл подхватился: временно вставьте в шаблон маркер `TEST-SEARCHING` и обновите `/searching`. Маркера нет → файл не там или кэш не сброшен.

Если обвязка у вас в регионах — ничего из этого не нужно, страница уже рендерится в `page.html.twig` со всеми блоками.

### Вариант без правок темы: блок «Custom Search results»

Когда каркас сайта зашит в node-шаблоны и трогать их не хочется, выдачу можно вынести на **обычную страницу-ноду** — она получит полный каркас (шапку, город, подвал), как все страницы:

1. Создайте страницу «Поиск» с адресом, например, `/poisk`.
2. `/admin/structure/block` → разместите блок **«Custom Search results»** в регионе содержимого, видимость — только эта страница. Блок рисует форму (отправляется на текущий адрес), выдачу и пагинацию.
3. Чтобы Enter из футерного поиска вёл на эту страницу: `/admin/config/search/custom-search` → таб **«Main settings»** → поле **«Страница выдачи (URL)»** → `/poisk`. Пусто — работает стандартный `/searching`.

Страницы с непустым запросом (`?q=…`) по-прежнему отдают `noindex, follow`; страница без запроса остаётся обычной.

### Исключение из XML-карты сайта (simple_sitemap)

Страница выдачи не должна попадать в sitemap: отдельной ценности для поисковиков она не несёт, а варианты `?q=…` — бесконечные дубли. Если установлен модуль [simple_sitemap](https://www.drupal.org/project/simple_sitemap) (3.x и 4.x), Custom Search **автоматически убирает `/searching`** из генерируемой карты на этапе генерации (hook_simple_sitemap_links_alter + hook_simple_sitemap_custom_links_alter) — даже если добавить её в custom links вручную.

Дополнительно на `/searching` отдаётся `<meta name="robots" content="noindex, follow">`, чтобы поисковики не держали страницу в индексе.

### Настройки: `/admin/config/search/custom-search`

- Таб **«Main settings»**: плейсхолдер, мин. длина запроса, лимиты подсказок/выдачи, типы материалов, показ метки типа.
- Таб **«Описание (RU)»**: описание + заголовок выдачи (RU) + текст «ничего не найдено» (RU, `@q` = запрос).
- Таб **«Description (EN)»**: то же на английском.
- Таб **«Оформление (Style)»**: цвета акцента, фона подсказок, текста и подсветки совпадений, скругления кнопок/карточек, тени, размер кнопки (применяются к подсказкам и странице выдачи).

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

- Drupal `^9.2 || ^10 || ^11`
  - Drupal 9: versions **9.2–9.5** (9.0 and 9.1 are not supported — they are long out of support and lack the `core/once` library the module is built on).
  - Drupal 10: all current versions (verified on 10.6.8).
  - Drupal 11: all current versions.
- PHP >= 7.4 (Drupal 9 targets PHP 7.3–8.1, Drupal 10 requires PHP 8.1+, Drupal 11 requires PHP 8.3+)
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

### Results page and your theme

`/searching` is a normal Drupal route returning a render array, so it **always renders inside the theme's main template** (`html.html.twig` → `page.html.twig`) with all regions and blocks. The module ships no bare-bones template of its own; core applies the `page__searching` suggestion automatically.

If header/menu/footer are missing and a `page--searching.html.twig` copied from `page.html.twig` changed nothing, your theme keeps the site chrome **outside regions** (the `page.header`/`page.footer` regions are empty on every page — the 404 page shows the same) and hardcodes it in the **node template** (`node--page.html.twig` / `node.html.twig` in the theme's `templates/` — the tell-tale sign is `.page-header` markup inside). The module cannot pull it in: node templates apply to nodes only, and the results page is not a node.

Recipe (in this order):

1. Open `templates/node--page.html.twig` of your theme (or `node.html.twig` — the file containing `.page-header`).
2. Create `templates/page--searching.html.twig` as a copy of that file, but replace the node innards (`{{ content }}`, label, …) with `{{ page.content }}`. Header = everything before the content, footer = everything after.
3. Clear caches (`drush cr` or Configuration → Performance → "Clear all caches") — Twig will not see the new file otherwise.
4. Sanity check: temporarily put a `TEST-SEARCHING` marker into the template and reload `/searching`. No marker → wrong location or cache not cleared.

If your theme keeps the chrome in regions, none of this is needed — the page already renders inside `page.html.twig` with all blocks.

### No-theme-edit option: the "Custom Search results" block

When the site chrome is hardcoded in node templates and you'd rather not touch them, move the results onto a **regular page node** — it gets the full chrome (header, city switcher, footer) like every other page:

1. Create a "Search" page, e.g. at `/poisk`.
2. `/admin/structure/block` → place the **"Custom Search results"** block into the content region, visibility limited to that page. The block renders the form (submits to the current path), results and the pager.
3. To point the footer search at this page: `/admin/config/search/custom-search` → **Main settings** tab → **"Results page URL"** → `/poisk`. Leave empty for the default `/searching` route.

Pages with a non-empty query (`?q=…`) still serve `noindex, follow`; the page without a query stays a normal indexable page.

### Exclusion from the XML sitemap (simple_sitemap)

Results pages should not be in the sitemap: no standalone value for search engines, and every `?q=…` variant is a duplicate. With [simple_sitemap](https://www.drupal.org/project/simple_sitemap) (3.x and 4.x) installed, Custom Search **automatically removes `/searching`** from generated sitemaps (hook_simple_sitemap_links_alter + hook_simple_sitemap_custom_links_alter) — even if the link is added to custom links manually.

Additionally, `/searching` serves `<meta name="robots" content="noindex, follow">` so search engines drop it from the index.

### Settings: `/admin/config/search/custom-search`

- **Main settings** tab: placeholder, min length, suggestion/results limits, bundles, type label.
- **Описание (RU)** tab: Russian description + results header + “nothing found” text (`@q` = query).
- **Description (EN)** tab: the same in English.
- **Оформление (Style)** tab: accent, dropdown background, text and match-highlight colors, button/card radius, shadows, button size (applied to suggestions and results).

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
