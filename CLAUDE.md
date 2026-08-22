
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
