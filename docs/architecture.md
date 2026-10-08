# Architecture

Status: technical foundation (phase 1). Content types, real design and CMS
screens follow in later phases.

## Overview

```
Browser
   │  HTTPS
   ▼
Apache (goneo, .htaccess)  ── serves public/build/* and other static files directly
   │
   ▼
public/index.php ── Laravel 13 (PHP 8.4)
   │
   ├── Public website      routes/public.php   middleware group "public" (stateless)
   │     Blade, server-rendered, no session, no cookies
   │
   └── Employee backend    routes/admin.php    middleware group "web" (session + CSRF)
         /verwaltung/…     Blade, server-rendered, auth + MFA + permissions
   │
   ▼
MySQL (one database: content, users, sessions, cache, audit log)
```

## Decisions and reasons

| Decision | Why |
|---|---|
| **Laravel monolith** | One codebase, one deployment, one database. A municipality website plus CMS is a classic CRUD application; splitting it into services or a headless CMS adds operational cost without benefit, and would not fit shared hosting. Laravel is mature, well documented and long-term maintained, so future developers can take over easily. |
| **Same application for website and backend** | Shared models, validation, authorization and URL generation; no API between the two that could drift or leak. Separation is enforced inside the app (route files, middleware groups, controller namespaces, view folders, CSS entry points). |
| **Blade / server rendering** | Fast first render, works without JavaScript, best baseline for accessibility (real links, forms and buttons), no client-side routing, trivial caching, strict CSP is easy because there is no inline script. |
| **Minimal JavaScript** | Only for genuine enhancements (progressive enhancement). Every feature must work without JS. No React/Vue for the CMS shell. |
| **PHP 8.4** | Target version of the goneo package (8.5 is available but 8.4 is the conservative choice). `composer.json` pins the platform to 8.4 so the lock file always resolves for production. |
| **MySQL, one production database** | Available on goneo; used for everything that needs persistence (incl. sessions, cache, rate limits). Local development and the test suite also use MySQL – never SQLite – so behaviour (strict mode, collations, foreign keys, time zones) matches production. |
| **No Node.js runtime, no Composer on the server** | Node/Vite only compiles CSS/JS and Composer only installs `vendor/` – both at build time, in the Linux PHP 8.4 development environment. Production receives a self-contained release artifact (`scripts/release/build.sh`) and serves static files from `public/build`. |
| **No Redis, no queue worker** | Not available on shared hosting. Sessions, cache and rate limiting use the database driver; queues run `sync`. Password-reset mails are sent after the response via `defer()` (no worker). |
| **No external search server** | Search will be implemented with MySQL (see below). |
| **No cron dependency for publishing** | goneo's WebCron is limited. Visibility is computed from timestamps at request time (see below). |

## Directory structure

```
app/
  Console/Commands/        admin:create, admin:reset-mfa, permissions:sync, audit:prune, deploy:check
  Http/
    Controllers/Public/    public website (currently placeholder + robots.txt)
    Controllers/Admin/     backend; Auth/ (login, MFA challenge, password reset), Account/ (MFA setup)
    Middleware/            security headers, admin headers, indexing, HTTPS, MFA, session checks
  Logging/                 log redaction (Monolog tap)
  Models/                  User, AuditEvent, TwoFactorRecoveryCode
  Policies/                UserPolicy
  Services/                Auth (login flow, TOTP), Audit, Authorization (role sync), Uploads
  Session/                 privacy-preserving database session handler
  Support/                 AdminArea, SearchEngineIndexing, SiteTime, Authorization enums
bootstrap/app.php          routing areas, middleware groups, exception settings
config/admin.php           backend path, MFA policy, throttling, session lifetime
config/audit.php           audit-event retention
config/security.php        HTTPS, canonical/trusted/redirect hosts, proxies, HSTS, CSP
config/site.php            site time zone, search-engine indexing switch
config/uploads.php         upload allowlist and limits
routes/public.php          public routes (stateless)
routes/admin.php           backend routes (prefix /verwaltung)
resources/views/
  layouts/                 base (document skeleton), public, admin
  components/              form field, error summary, status message
  public/  admin/  errors/
resources/css/             tokens.css, base.css, components/, pages/, entry points app.css + admin.css
resources/js/              app.js (empty entry point for future progressive enhancement)
tests/Feature/{Public,Admin,Security}, tests/Unit, tests/Browser (Playwright + axe)
docker/                    optional local development environment
scripts/release/           build.sh (release artifact), activate.sh (runs on goneo)
docs/                      this documentation
```

## Request handling

**Global middleware** (every request, including 404s): trusted hosts, trusted
proxies, `SecurityHeaders`, `AdminAreaHeaders` (no-store + noindex for
everything below `/verwaltung`), `SearchEngineIndexingHeader`,
`CanonicalUrlRedirect` (HTTP → HTTPS and alias hosts → canonical host in a
single 301, path and query preserved).

**`public` group**: only route-model binding. No session, no cookie
encryption, no CSRF token → anonymous visitors receive **no cookies**. A future
stateful public feature (contact form) opts in per route with
`->middleware('web')`.

**`web` group** (backend): encrypted cookies, database session, CSRF
(token + `Sec-Fetch-Site` check, no `XSRF-TOKEN` cookie), shared validation
errors.

**Backend route middleware** (`routes/admin.php`):

1. `guest` – login, MFA challenge, password reset.
2. `auth`, `auth.session` (logs out other sessions after password change),
   `admin.session` (deactivated accounts, absolute session lifetime).
3. `admin.mfa` (MFA policy) and `can:admin.access` for everything except MFA
   enrolment and logout.

Backend routes are registered **before** public routes so that a future public
catch-all route can never shadow them.

## Authorization

- Permissions (`App\Support\Authorization\Permission`) and roles with default
  permissions (`Role`) are defined in code, synchronised into the database by
  `php artisan permissions:sync` (idempotent, run on every deployment) using
  `spatie/laravel-permission`.
- Code checks **permissions**, never role names: `can:` middleware, policies
  (`UserPolicy`), `@can` in Blade.
- Prepared roles: Administrator, Chefredaktion, Fachbereichsredaktion,
  Veranstaltungsredaktion, Prüfer/Reviewer. For now all may enter the backend;
  only administrators manage accounts and read the audit log. Content
  permissions are added together with the content types.

## Time zones

- **Internal/database time is UTC**: `config('app.timezone')` is fixed to
  `UTC`, the MySQL session time zone is `+00:00`. All timestamps (incl.
  `publish_at`, `expires_at`, audit log, sessions) are stored and compared in
  UTC.
- **Citizen/editor-facing time is `SITE_TIMEZONE`** (`config('site.timezone')`,
  default `Europe/Berlin`). Every date/time that is displayed or entered goes
  through `App\Support\SiteTime` – never format a stored timestamp directly:

  | Use | Method |
  |---|---|
  | Display | `SiteTime::format($utc)` → `29.03.2026, 03:00` |
  | Pre-fill `<input type="datetime-local">` | `SiteTime::toInput($utc)` |
  | Store editor input (publish/expiry) | `SiteTime::fromInput($request->input('publish_at'))` → UTC |
  | Warn about the repeated DST hour | `SiteTime::isAmbiguous($value)` |
  | "Now" for display | `SiteTime::now()` |

- DST rules for input: a wall-clock time that does not exist (the skipped hour
  when clocks go forward, e.g. 29.03.2026 02:30) is **rejected** with a
  validation error instead of being silently shifted. For the hour that occurs
  twice (e.g. 25.10.2026 02:30) the **later** occurrence is used, so scheduled
  content never appears too early; forms should show a hint.
- Comparisons (`publish_at <= now()`) always happen in UTC and are therefore
  independent of DST (tested in `tests/Unit/SiteTimeTest.php`).

## Scheduled publication (future content)

Visibility is a query condition, not a job:

```sql
WHERE status = 'published'
  AND publish_at <= UTC_TIMESTAMP()
  AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
```

implemented as an Eloquent scope using `now()` (UTC). Content therefore appears
and disappears exactly on time even if no cron job ever runs. Composite indexes
on `(status, publish_at, expires_at)` will be added with the content tables.
Caches of public pages must respect the next `publish_at`/`expires_at`.

## URLs, redirects, SEO (prepared, not implemented)

- Canonical origin: `https://www.gemeinde-merching.de` (`APP_URL`). The
  existing host and existing paths are preserved. Alias hosts in
  `REDIRECT_HOSTS` (the non-www domain) and plain HTTP are redirected in one
  301 hop with path and query unchanged (`CanonicalUrlRedirect`); unknown
  hosts get HTTP 400.
- Public URLs are explicit paths/slugs stored per content item; they never
  encode database IDs or the menu hierarchy. Old URLs of the previous website
  can be kept 1:1 or mapped via a `redirects` table (`source_path` unique →
  `target`, status 301/410) checked in a fallback route.
- Canonical URLs are generated from `APP_URL` (forced root URL in production).
- XML sitemaps will be generated by a controller from published content (no
  cron), cached in the database cache. Backend URLs never appear in sitemaps.
- `robots.txt` is dynamic (`RobotsController`): `Disallow: /` unless
  `APP_ENV=production` **and** `PUBLIC_INDEXING=true`. There is deliberately no
  static `public/robots.txt`.
- Structured metadata (Open Graph, schema.org `GovernmentOrganization`, events)
  will be rendered server-side in the layout.

## Search (prepared, not implemented)

MySQL only: a denormalised `search_index` table (type, id, title, body text,
URL, publish window) maintained synchronously on save, with a `FULLTEXT` index
(InnoDB, natural-language/boolean mode). German specifics (umlauts, compound
words, minimum token size `innodb_ft_min_token_size`) must be verified on the
goneo MySQL version; a `LIKE`-based fallback for short terms is acceptable for
the expected data volume.

## Files and uploads

See [security.md → Uploads](security.md#uploads). Originals are stored outside
the web root (`storage/app/private`), delivered only through controllers.

## Database

All schema changes are versioned migrations; production never needs manual
SQL. Current tables (phase 1):

| Table | Purpose |
|---|---|
| `users` | employee accounts incl. encrypted TOTP secret, active flag |
| `password_reset_tokens` | hashed reset tokens (Laravel broker) |
| `sessions` | server-side sessions (no IP / user agent columns) |
| `cache`, `cache_locks` | database cache (rate limiting, permission cache) |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | authorization (spatie/laravel-permission) |
| `two_factor_recovery_codes` | HMAC-hashed single-use recovery codes |
| `audit_events` | append-only audit log without network data; retention `AUDIT_RETENTION_DAYS` (default 730) |
| `migrations` | Laravel migration bookkeeping |

Charset `utf8mb4`, collation `utf8mb4_unicode_ci` (portable to MySQL 5.7/8.x
and MariaDB), engine InnoDB, foreign keys with explicit delete behaviour.
Polymorphic columns store morph-map aliases (`user`), not class names.
