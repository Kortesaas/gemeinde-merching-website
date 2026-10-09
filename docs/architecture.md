# Architecture

Status: technical foundation (phase 1) and content/domain foundation
(phase 2, see [content-model.md](content-model.md)). Real design, final CMS
screens follow in later phases. [Site-wide functional services](site-foundation.md) are now implemented.

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
| **No external search server** | Search is implemented with MySQL (see below). |
| **No cron dependency for publishing** | goneo's WebCron is limited. Visibility is computed from timestamps at request time (see below). |

## Directory structure

```
app/
  Admin/                   functional CMS: ContentResource (save workflow), Resources/ (one per entity),
                           Fields/ (form field types), ResourceRegistry, Options
  Console/Commands/        admin:create, admin:reset-mfa, permissions:sync, audit:prune, revisions:prune, deploy:check
  Contracts/               Routable, Revisionable, Searchable
  Enums/                   PublicationStatus/State, AccessibilityStatus, CategoryContext, …
  Exceptions/              DomainRuleViolation (business rule → accessible form error)
  Http/
    Controllers/Public/    placeholder, robots.txt, ContentController (DB routes & redirects)
    Controllers/Admin/     Auth/, Account/, ResourceController (generic CRUD), Placement-, Revision-,
                           DocumentFile-, UserController
    Middleware/            security headers, admin headers, indexing, canonical URL, trailing slash, MFA, sessions
    Requests/Admin/        ResourceRequest (authorization incl. publish rules + validation)
  Logging/                 log redaction (Monolog tap)
  Models/                  content & directory models, PublicRoute, Redirect, NavigationItem,
                           ContentRevision, SourceReference, User, AuditEvent, …
  Models/Concerns/         HasPublication, HasRevisions, HasPublicRoute, Has*Placements, TracksEditors, …
  Policies/                ContentPolicy + one subclass per model, UserPolicy
  Rules/                   SafeUrl, SiteDateTime, RecurrenceRule
  Services/                Auth, Audit, Authorization, Content (publication, revisions, documents,
                           usage), Routing (RouteManager, RedirectManager), Uploads
  Session/                 privacy-preserving database session handler
  Support/                 AdminArea, SiteTime, MorphMap, Routing/PublicPath, Content/SafeMarkdown,
                           Search/SearchDocument, Authorization (ContentType, Ability, Permission, Role)
bootstrap/app.php          routing areas, middleware groups, exception settings
config/admin.php           backend path, MFA policy, throttling, session lifetime
config/audit.php           audit-event retention
config/revisions.php       content-revision retention (pending policy)
config/security.php        HTTPS, canonical/trusted/redirect hosts, proxies, HSTS, CSP
config/site.php            site time zone, search-engine indexing switch
config/uploads.php         upload allowlist and limits
routes/public.php          public routes (stateless) incl. the content fallback route
routes/admin.php           backend routes (prefix /verwaltung), one route set per admin resource
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

**`public` group**: trailing-slash normalisation and route-model binding.
No session, no cookie encryption, no CSRF token → anonymous visitors receive
**no cookies**. The contact form opts in per
route with `->middleware('web')`. The last public route is a fallback that
resolves database-managed URLs (`ContentController`).

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

## Scheduled publication

Implemented for all publishable content (see
[content-model.md → Publication lifecycle](content-model.md#publication-lifecycle)).
Visibility is a query condition, not a job:

```sql
WHERE status = 'published'
  AND publish_at <= UTC_TIMESTAMP()
  AND (expires_at IS NULL OR expires_at > UTC_TIMESTAMP())
```

implemented as the Eloquent scope `visible()` using `now()` (UTC). Content
therefore appears and disappears exactly on time even if no cron job ever
runs. Every publishable table has a composite index on
`(status, publish_at, expires_at)`. Caches of public pages must respect the
next `publish_at`/`expires_at`.

## URLs, redirects, SEO

- Canonical origin: `https://www.gemeinde-merching.de` (`APP_URL`). The
  existing host and existing paths are preserved. Alias hosts in
  `REDIRECT_HOSTS` (the non-www domain) and plain HTTP are redirected in one
  301 hop straight to the final target (`CanonicalUrlRedirect`); unknown hosts
  get HTTP 400.
- **Canonical paths have no trailing slash** (root stays `/`). Legacy URLs
  with a slash reach the slashless canonical URL (or a legacy redirect's
  destination) in a single 301, path and query preserved.
- Implemented: database-managed URLs (`public_routes`) independent of IDs and
  navigation, redirects with loop/chain/collision protection, one-hop
  resolution, `<link rel="canonical">`. Details:
  [content-model.md → URL model](content-model.md#url-model).
- **Navigation is not URL structure**: menus reference a record's canonical
  route; moving a page in the menu never changes its URL.
- XML sitemaps and server-rendered SEO/Open Graph are implemented; current
  visibility is evaluated on request without stale caching (see site-foundation.md).
- `robots.txt` is dynamic (`RobotsController`): `Disallow: /` unless
  `APP_ENV=production` **and** `PUBLIC_INDEXING=true`. There is deliberately no
  static `public/robots.txt`.
- Open Graph is rendered server-side. A `StructuredDataProvider` contract
  prepares later Schema.org output from verified records; no data is invented.

## Search (implemented foundation)

The index and query services are implemented in `Services/Search` (see
[site-foundation.md](site-foundation.md#search-architecture)); the final public
search UI follows later. Models expose text via `Searchable::toSearchDocument()`.
The denormalised `search_entries` table stores type/id and title, summary,
keywords, body with an InnoDB FULLTEXT index (boolean mode); it is maintained
synchronously on save. Current public visibility/routes are queried against
source records, so scheduled activation/expiry does not depend on a rebuild.
A literal `LIKE` fallback covers short terms and compounds. Verify FULLTEXT
settings and German query performance on the goneo MySQL installation.

## Files and uploads

See [security.md → Uploads](security.md#uploads) and
[content-model.md → Document model](content-model.md#document-model).
Originals are stored outside the web root (`storage/app/private`), delivered
only through controllers, and never physically deleted while referenced.

## Database

All schema changes are versioned migrations; production never needs manual
SQL. Content and routing tables (phase 2) are described in
[content-model.md](content-model.md#entities). System tables (phase 1):

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
