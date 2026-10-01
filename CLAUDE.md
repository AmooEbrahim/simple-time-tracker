
## Stack

Laravel 13 (PHP 8.3) + Inertia.js 2 + Vue 3 + Vite + Tailwind 3. SQLite database (`database/database.sqlite`). Auth scaffolded via Laravel Breeze (Vue flavor). Frontend route names come from Ziggy (`vendor/tightenco/ziggy`).

## Commands

- `composer dev` — run the full dev stack concurrently: `php artisan serve`, `queue:listen`, `pail` (log tailer), and `npm run dev` (Vite).
- `composer test` — clears config, then `php artisan test` (PHPUnit 12). Uses in-memory SQLite (see `phpunit.xml`).
- Single test: `php artisan test --filter=TestClassName` or `php artisan test tests/Feature/ProfileTest.php`.
- `npm run build` — production JS/CSS build.
- `php artisan migrate` — run migrations against `database/database.sqlite`.
- `./vendor/bin/pint` — code formatter (Laravel Pint).
- `composer setup` — one-shot bootstrap (install, `.env`, key, migrate, npm install, build).

## Architecture

Single-user time-tracking SPA served via Inertia. All HTTP routes are defined in `routes/web.php` (plus `routes/auth.php` for Breeze). There is no API — the Vue pages receive props from Inertia controllers.

### Domain model

- `User` hasMany `Project`, `TimeEntry`, `Tag`.
- `TimeEntry` belongsTo `User` + nullable `Project`, belongsToMany `Tag`. `duration_seconds` is denormalized and recomputed whenever `started_at`/`ended_at` changes (see `TimeEntry::stop` and `TimeEntryController::update`). A "running" timer is a `TimeEntry` where `ended_at IS NULL`.
- `Tag` is per-user (unique on `user_id`+`name`) and created on the fly via `tag_names` in form requests (see `TimeEntryController::syncTags`).
- `Project` and `TimeEntry` use `SoftDeletes`. `Tag` does not.

### tempo CLI

`tempo` (repo root wrapper → `php artisan tempo`) is the terminal companion for
this project — same MySQL data as the web UI, no separate store. Implementation:
`app/Console/Commands/TempoCommand.php` (+ `tests/Feature/TempoCommandTest.php`).
User defaults to `--user=<id>` / `TEMPO_USER_ID` env / `1`. Times typed are
Asia/Tehran local, stored UTC. Never deletes anything. Highlights: bare
`tempo <project>` fuzzy-searches (1 → start, many → pick, 0 → offer create),
`-m/--message` sets the description on start/stop/update, `-i/--id` shows or
updates one entry, `-l/--stats/--csv` take `today|yesterday|week|month|all` plus
`--project=` filter, `--add` creates manual entries, `--prompt` is for PS1.

**Interactive mode** (`app/Console/Commands/Concerns/TempoInteractive.php`, built on
`laravel/prompts`, already shipped with the framework): bare `tempo` on a real
terminal opens an arrow-key menu (start/stop/switch/resume, browse & edit entries,
manual-entry wizard, stats, projects). `tempo --status` skips it. Project picking is a
plain `select` (recent first, "+ Create" inline) up to `PICK_LIST_MAX` choices, then a
type-to-filter `search`. Ambiguous `tempo <name>` matches use a `select` too. Gate every
new prompt behind `canPrompt()` (menu: `canShowMenu()`): Symfony reports "interactive"
even for piped stdin, but prompts crash without a TTY, and scripts/cron must keep
getting plain output. Tests drive prompts through Laravel's Symfony fallback
(`expectsQuestion`; a `search` asks the query first, then the choice).

**Esc = back** inside the menu: `app/Console/Support/EscapeBackTerminal.php` turns a bare
Esc into prompts' built-in revert key (Ctrl+U → `FormRevertedException`). Each screen catches
it and steps back one level (wizard step → previous step → main menu; main menu → quit).
It is only enabled by `runMenu()`, so `tempo -e` etc. are unaffected. It swaps prompts'
protected static terminal via reflection, so re-check it after upgrading `laravel/prompts`
(the fallback is "no Esc", Ctrl+U still works). Esc can't be unit-tested through the Symfony
fallback — verify in a real terminal.

### TimeTrackingService

`app/Services/TimeTrackingService.php` is the central domain service — controllers stay thin and delegate here. Important behaviors:

- **Single running timer invariant**: starting a new timer stops any existing running timer first (`TimeEntryController::store` and `restart`). Preserve this when adding timer-start paths.
- **Cursor pagination** on the dashboard uses a composite `started_at|id` cursor (not Laravel's built-in cursor pagination), fetching `PER_PAGE + 1` rows to detect `hasMore`. The running timer is prepended to the first page only.
- **Week starts on Saturday** (`getWeekStart`) — not Laravel's default Monday. Report presets in `ReportController` use Laravel's default `startOfWeek()`, which is Monday; the dashboard week headers use Saturday. Be deliberate about which one you want.
- Reports group the same underlying entries three different ways (by project, by date, by tag) in a single request. The tag grouping fans out per-tag and includes a synthetic `no_tag` bucket.

### Request/response flow

- Authorization lives in `app/Policies/{Project,TimeEntry}Policy.php` and is called via `$this->authorize(...)` in controllers. Ownership is by `user_id`.
- Validated form requests (`app/Http/Requests/*`) — `StoreTimeEntryRequest` accepts either a "start now" payload (no `ended_at`) or a manual entry (with `ended_at`); the controller branches on this.
- `ReportController::export` streams CSV (filename is `.xlsx` but content is CSV — if a user reports a broken Excel file, that's why).

### Frontend

Pages live in `resources/js/Pages/` and are resolved by name in `app.js` (e.g. Inertia `render('Dashboard')` → `Pages/Dashboard.vue`). Shared UI in `Components/`, layouts in `Layouts/`. Use the `route()` helper (Ziggy) for named routes.

### Local Browsing
main Url is `timetracker.loc`
account to login with:
	- email: `admin@timetracker.loc`
	- password: `password`
