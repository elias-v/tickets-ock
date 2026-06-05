# OCK-Tickets – Agent Instructions

Stack: vanilla PHP 8.0+, MySQL/MariaDB, Apache, PHPMailer via Composer (only dep).
No tests, no linter, no type checker, no build step, no CI, no npm.

## Setup & Verification

- `composer install --no-dev` — installs PHPMailer
- `php db_schema.php` — creates DB `vabibese_tickets` (or import `schema.sql`)
- Config via `.env` (gitignored) or env vars; `config.php` parses `.env` manually
- DB_HOST may include port (`host:3306`) — handled in DSN
- Default admin: `admin` / `admin`
- No test command exists. Use `php -l <file>` for syntax check after changes.

## Architecture

- `config.php` — PDO singleton, `sendEmail()`, `getSetting()`, `jsonResponse()`
- `index.php` — customer frontend (seat grid + order form)
- `api/` — JSON endpoints: get-seats, reserve, confirm, admin-update-seat, reservation-by-seat
- `admin/` — login, dashboard, CSV export
- `cron/cleanup.php` — expires pending reservations + purges rate_limits > 24h
- `assets/` — vanilla JS (`app.js`, `seat-grid.js`) + CSS

## Key Patterns

- **Seat status**: `seats.status` ENUM is `available|reserved|disabled`. The effective `pending` and `reserved` shown in the UI are **derived from the `reservations` table** by `get-seats.php`. Never rely on `seats.status` for pending seats. To fix orphaned seats, reset `seats.status = 'available'` for seats not in active reservations.
- **Double-booking prevention**: `reserve.php` wraps check+INSERT in a transaction with `SELECT ... FOR UPDATE`. `confirm.php` uses a transaction with `rowCount()` check on seat UPDATE — rolls back if seats are already taken.
- **CSRF**: All admin POST forms and `admin-update-seat.php` validate a session-bound CSRF token. Any new admin POST endpoint must do the same.
- **`total_amount` recalculation**: `admin-update-seat.php` recalculates `total_amount` when removing a seat from a reservation (category + discount + delivery surcharge).
- **Auto-migration**: Most API files have try/catch `ALTER TABLE ADD COLUMN` blocks at the top. Schema evolves at runtime. Safe to re-run. (Not in `get-seats.php`.)
- **Rate limiting**: `rate_limits` table — 5 req/h per IP for reservations, 5 req/15min per IP for admin login
- **Sitzplan**: rows 2–26, sections `left|right|front_left|front_right`, cat 1 (pink, rows 3–15), cat 2 (blue, rest). Bodan on rows 2–3, 8, 11, 14, 17
- **Email**: PHPMailer SMTP — STARTTLS if credentials set, else localhost:25 no auth
- **Token**: `bin2hex(random_bytes(32))` — 64 hex chars, 24h expiry
- **All UI in German** — errors, form labels, email templates

## Gotchas

- No test/lint/typecheck commands
- No framework — plain PHP with `require_once` includes
- CORS restricted to `https://tickets.oratorienchor-kreuzlingen.ch` in API files
- `.htaccess` blocks `config.php`, `db_schema.php`, `composer.*`, `.env` but **not** `cron/`
- Frontend cache-busts CSS/JS with `?v=<?= time() ?>` — no real versioning
- `display_errors = 0` in `config.php` — errors go to server log, not browser
