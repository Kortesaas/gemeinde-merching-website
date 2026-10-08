# Gemeinde Merching – Website & Verwaltung

Official website and employee CMS of Gemeinde Merching
(<https://www.gemeinde-merching.de>): a single, server-rendered **Laravel 13**
application on **PHP 8.4** and **MySQL**, designed to run on goneo shared
hosting (Apache; no Docker, Node.js, Composer or Redis in production).

> **Status:** technical foundation. Public website, content types, design and
> CMS screens are not built yet.

## What is there

- Public area (stateless, no cookies, no third-party requests) with a
  placeholder page.
- Employee backend under `/verwaltung`: login, TOTP two-factor authentication
  with recovery codes, password reset, role/permission foundation, dashboard
  placeholder.
- Security headers with strict CSP, hardened sessions, rate limiting, audit
  log without network data, upload validation foundation.
- Accessible base layout and forms (WCAG 2.2 AA target).
- Tests: PHPUnit (MySQL) + Playwright/axe browser checks.

## Quick start (Docker)

```bash
cp .env.example .env
sed -i.bak "s/^DB_PASSWORD=$/DB_PASSWORD=$(openssl rand -hex 16)/" .env && rm .env.bak
docker compose up -d --build
docker compose exec app composer install
docker compose exec app php artisan key:generate
docker compose exec app php artisan migrate
docker compose exec app php artisan permissions:sync
npm ci && npm run build
docker compose exec app php artisan admin:create
```

Open <http://localhost:8088> (website) and
<http://localhost:8088/verwaltung/login> (backend). Mails: <http://localhost:8025>.

Native PHP/MySQL setup and details: [docs/local-development.md](docs/local-development.md).

## Commands

| | |
|---|---|
| `composer test` | PHPUnit test suite (MySQL testing DB) |
| `composer lint` / `composer format` | Laravel Pint check / fix |
| `composer analyse` | Larastan (PHPStan level 8) |
| `composer audit` | PHP dependency vulnerabilities |
| `composer check` | all of the above |
| `npm run build` / `npm run dev` | compile assets / Vite dev server |
| `npm test` | Playwright + axe accessibility & privacy checks (app running) |
| `npm run audit` | npm dependency vulnerabilities |
| `php artisan admin:create` | create an administrator interactively |
| `php artisan admin:reset-mfa <email>` | remove MFA from an account (lock-out recovery) |
| `php artisan permissions:sync` | sync code-defined roles/permissions |
| `php artisan audit:prune` | delete audit events older than `AUDIT_RETENTION_DAYS` |
| `php artisan deploy:check` | verify production configuration |
| `scripts/release/build.sh` | build the deployable release artifact (vendor + assets included) |

(With Docker, run PHP commands via `docker compose exec app …`.)

## Documentation

- [Architecture](docs/architecture.md) – decisions, structure, database, routing
- [Local development](docs/local-development.md)
- [Deployment to goneo](docs/deployment-goneo.md)
- [Security](docs/security.md) · [SECURITY.md](SECURITY.md)
- [Privacy](docs/privacy.md)
- [Accessibility](docs/accessibility.md)
- [Backups](docs/backups.md)

## Ground rules

- Never commit `.env`, credentials, uploads, dumps or `designsystem-inspiration/`.
- Every schema change is a migration.
- No external resources (fonts, CDNs, trackers) without an explicit privacy
  decision; no inline scripts/styles (CSP).
- Every page must work without JavaScript and be keyboard- and
  screen-reader-accessible.
