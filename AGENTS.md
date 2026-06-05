# OCK-Tickets – Agent Instructions

Stack: vanilla PHP 8.0+, MySQL/MariaDB, Apache, PHPMailer via Composer.
No tests, no linter, no type checker, no build step, no CI, no npm.

## Setup

- `composer install --no-dev` — installs PHPMailer (only dep)
- `php db_schema.php` — creates DB `vabibese_tickets` with all tables + sample seat data
- Alternative: import `schema.sql` directly
- Config via `.env` (gitignored) or environment variables; `config.php` parses `.env` manually (no phpdotenv)
- Default admin: `admin` / `admin` — change on first login

## Verification

No test command exists. After changes:
- `php -l <file>` — syntax check on modified files
- `php -S localhost:8000` — built-in dev server for manual smoke test

## Architecture

- `config.php` — bootstrap: PDO singleton, `sendEmail()` (PHPMailer), `getSetting()`, `jsonResponse()`
- `index.php` — customer frontend (seat grid + order form)
- `api/` — JSON endpoints: get-seats, reserve, confirm, admin-update-seat, reservation-by-seat
- `admin/` — admin panel: login, dashboard, CSV export
- `cron/cleanup.php` — expires pending reservations + purges rate_limits > 24h
- `assets/` — vanilla JS (`app.js`, `seat-grid.js`) + CSS (`style.css`)

## Key Patterns

- **Auto-migration**: Each API file has try/catch `ALTER TABLE ADD COLUMN` blocks. Schema evolves at runtime. Safe to re-run.
- **Config override**: env vars > `.env` > hardcoded defaults in `config.php`
- **Rate limiting**: `rate_limits` table — 5 req/h per IP for reservations, 5 req/15min per IP for admin login
- **Seat statuses**: `available`, `reserved` (confirmed), `pending` (unconfirmed), `disabled` (admin), `is_bodan` (via Bodan Papeterie)
- **Sitzplan**: rows 2–26, sections `left|right|front_left|front_right`, cat 1 (pink, rows 3–15), cat 2 (blue, rest). Bodan on rows 2–3, 8, 11, 14, 17
- **Email**: PHPMailer SMTP — STARTTLS if credentials set, else localhost:25 no auth
- **Token**: `bin2hex(random_bytes(32))` — 64 hex chars, 24h expiry
- **All UI in German** — errors, form labels, email templates

## Gotchas

- No test/lint/typecheck commands exist
- No framework — plain PHP with `require_once` includes
- CORS restricted to `https://tickets.oratorienchor-kreuzlingen.ch` in API files
- `.htaccess` blocks direct access to `config.php`, `db_schema.php`, `composer.*`, `.env`
- Frontend cache-busts CSS/JS with `?v=<?= time() ?>` — no real versioning
