# Local development

Local development uses the same fundamental stack as production:
**Apache + PHP 8.4 + MySQL + Laravel**. Node.js is only used to compile
assets. Two ways to run it:

- **A) Docker Compose** (recommended, nothing else to install besides Docker
  and Node.js) – PHP 8.4 + Apache, MySQL 8.4, Mailpit.
- **B) Native** – your own PHP 8.4, Composer, MySQL and Node.js.

Docker is a development convenience only. The application itself does not
depend on it.

## Requirements

| | Docker setup | Native setup |
|---|---|---|
| Docker Desktop / Engine with Compose v2 | ✔ | – |
| PHP 8.4 (+ pdo_mysql, intl, mbstring, bcmath, gd, fileinfo) | in container | ✔ |
| Composer 2 | in container | ✔ |
| MySQL 8.x (or MariaDB 10.6+) | in container | ✔ |
| Node.js ≥ 22 + npm | ✔ (on host) | ✔ |

## A) Docker Compose

```bash
git clone git@github.com:Kortesaas/gemeinde-merching-website.git
cd gemeinde-merching-website

cp .env.example .env
# set DB_PASSWORD in .env to any local-only value, e.g.:
sed -i.bak "s/^DB_PASSWORD=$/DB_PASSWORD=$(openssl rand -hex 16)/" .env && rm .env.bak

docker compose up -d --build                 # app on http://localhost:8088
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan permissions:sync

npm ci
npm run build                                # or: npm run dev (hot reload)

docker compose exec app php artisan admin:create
```

- Website: <http://localhost:8088>
- Backend: <http://localhost:8088/verwaltung/login>
- Mailpit (password-reset mails): <http://localhost:8025>
- MySQL from the host: `127.0.0.1:33060` (user/password from `.env`)

The MySQL container creates a second database `merching_testing` for the test
suite on first start. Ports can be changed in `.env` (`APP_PORT`,
`FORWARD_DB_PORT`, `FORWARD_MAILPIT_DASHBOARD_PORT`).

Stop with `docker compose down`; `docker compose down -v` also deletes the
database volume.

## B) Native setup

```bash
cp .env.example .env            # set DB_HOST/DB_PORT/DB_DATABASE/DB_USERNAME/DB_PASSWORD
composer install
php artisan key:generate
```

Create the databases (as MySQL admin):

```sql
CREATE DATABASE merching CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE merching_testing CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'merching'@'localhost' IDENTIFIED BY '…local password…';
GRANT ALL ON merching.* TO 'merching'@'localhost';
GRANT ALL ON merching_testing.* TO 'merching'@'localhost';
```

```bash
php artisan migrate
php artisan permissions:sync
npm ci && npm run build
php artisan admin:create
```

Web server: point an Apache vhost's document root to `public/` with
`AllowOverride All` (closest to goneo). For quick work `php artisan serve` is
an acceptable substitute (it ignores `.htaccess`). Set `APP_URL` accordingly.
For password-reset mails either run Mailpit locally (SMTP `127.0.0.1:1025`) or use another local SMTP test transport. Contact messages deliberately refuse
the `log` mailer to avoid retaining message bodies.

SQLite is intentionally not supported as a substitute for MySQL.

## Everyday commands

Prefix with `docker compose exec app` when using Docker.

| Task | Command |
|---|---|
| PHP tests (MySQL `merching_testing`) | `composer test` |
| Code style check / fix | `composer lint` / `composer format` |
| Static analysis (Larastan level 8) | `composer analyse` |
| PHP dependency audit | `composer audit` |
| All PHP gates | `composer check` |
| Build assets | `npm run build` |
| Vite dev server with hot reload | `npm run dev` |
| Browser a11y/privacy tests (app must be running) | `npm test` |
| npm dependency audit | `npm run audit` |
| Production config check | `php artisan deploy:check` |
| Delete expired audit events | `php artisan audit:prune` |
| Build a release artifact (host, clean tree) | `scripts/release/build.sh` |

`npm test` needs the Playwright browser once: `npx playwright install chromium`
(or `--only-shell chromium`).

## Backend accounts locally

- `php artisan admin:create` – interactive; never pass passwords as arguments.
- MFA is required by default. Use any TOTP app (e.g. on your phone), or set
  `MFA_REQUIRED=false` in your local `.env` while working on unrelated parts.
  Never in production.
- Lost the authenticator locally? `php artisan admin:reset-mfa you@example.org`.

## Notes

- Running `npm run dev` writes `public/hot`; while it exists, Laravel loads
  assets from the Vite dev server and the CSP allows that origin (local only).
  Stop the dev server before running `npm test` against built assets.
- `designsystem-inspiration/` is a local reference export and is git-ignored;
  do not modify or commit it.
- Time: internal/database time is UTC; everything citizens and editors see or
  enter uses `SITE_TIMEZONE` (Europe/Berlin) via `App\Support\SiteTime`.
