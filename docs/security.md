# Security

This document describes the security controls of the foundation. Reporting
vulnerabilities: see [`SECURITY.md`](../SECURITY.md).

## Principles

- Secure defaults; anything weaker must be an explicit, documented setting.
- No hand-rolled cryptography: Laravel's hasher/encrypter, PHP's HMAC/CSPRNG,
  `pragmarx/google2fa` for TOTP (RFC 6238).
- Least privilege for accounts, roles and file permissions.
- The backend URL (`/verwaltung`, configurable via `ADMIN_PATH`) is **not** a
  security measure. Every backend route except login/recovery requires
  authentication, the MFA policy and a permission.

## Authentication (backend)

| Control | Implementation |
|---|---|
| No public registration | No registration routes exist (tested). Accounts are created by `php artisan admin:create` (first admin) and later by administrators. |
| Individual accounts | One account per employee; e-mail address is the login name. |
| Password hashing | bcrypt via Laravel's `hashed` cast (cost 12, automatic rehash on login). Argon2id could be enabled with `HASH_DRIVER=argon2id` if goneo's PHP build supports it. |
| Password policy | min. 12, max. 64 characters (length over complexity rules; max keeps input within bcrypt's 72-byte limit). |
| Generic errors | Same message for unknown account, wrong password and deactivated account; password-reset request always answers identically. |
| Timing | Login and reset requests are timeboxed (`AUTH_TIMEBOX_MICROSECONDS`, default 400 ms) and always perform one hash check; reset mails are sent after the response (`defer()`). |
| Brute force | 5 failed attempts per account+client and 20 per client → 5-minute lockout; 5 wrong second-factor codes per pending login; 5 reset requests/min per client (HTTP 429). Rate-limit keys contain only a SHA-256 hash of the IP, stored transiently in the cache table. |
| Session fixation | Session ID is regenerated **and the previous session destroyed** on every privilege change: password step, completed login, MFA enrolment. CSRF token is rotated at login. |
| Logout | POST only; session invalidated, token regenerated. |
| Session expiry | Idle timeout 60 min (`SESSION_LIFETIME`), session cookie ends with the browser, absolute lifetime 10 h (`ADMIN_SESSION_ABSOLUTE_LIFETIME`). Deactivated accounts are logged out on their next request. |
| Password reset | Laravel password broker: token stored hashed, valid 60 minutes, single use, one request per minute per account; only active accounts. After a reset all sessions of the account are deleted and the user must log in with password **and** second factor. |
| Password change elsewhere | `auth.session` middleware ends other sessions when the password hash changes. |
| Remember-me | Not supported (deliberately). |

### Multi-factor authentication (TOTP)

- Required by default (`MFA_REQUIRED=true`, also in production). Accounts
  without MFA can only reach the enrolment page and logout.
- Enrolment: secret (160 bit, Base32) generated server-side, shown as a QR code
  (rendered locally as SVG data URI – the secret never leaves the server) and
  as text; confirmed with a first valid code.
- Secret storage: encrypted with `APP_KEY` (AES-256, Eloquent `encrypted` cast).
  **Losing `APP_KEY` makes all MFA secrets unusable** – back it up (see
  [backups.md](backups.md)).
- Verification: ±1 time step tolerance; each time step can be used only once
  (`two_factor_last_used_timestep`) → no replay.
- Recovery codes: 10 single-use codes (~50 bit each) shown exactly once,
  rendered directly in the response (never stored in the session); stored only
  as HMAC-SHA256 (keyed with `APP_KEY`). Regenerating requires the current
  password.
- Lost device and no codes left: an administrator with SSH access runs
  `php artisan admin:reset-mfa user@example.org` after verifying the person's
  identity; the account must enrol again.
- Secrets, codes and recovery codes are never logged (see Logging).

## Authorization

Roles and permissions are defined in code and synchronised with
`php artisan permissions:sync` (stale permissions are removed). Checks go
through policies/`can:` middleware/`@can` – never `if ($user->role === …)`.
Content permissions are `<type>.<ability>` (view, create, edit, publish,
archive, delete, force-delete). Editing never implies publishing; permanent
deletion is a separate, administrator-only permission. Matrix and rules:
[content-model.md → Authorization](content-model.md#authorization-philosophy).
Every admin action authorizes **before** validating input (403 without leaking
validation details).

## Sessions

- Server-side in MySQL (`database` driver), payload encrypted
  (`SESSION_ENCRYPT=true`). Only the session ID is in the cookie.
- Custom handler stores **no IP address and no user agent**.
- Cookie: `HttpOnly`, `SameSite=Lax`, `Secure` + `__Host-` prefix in
  production (forced in `AppServiceProvider`, independent of `.env`).
- Garbage collection by lottery (2 % of requests) – no cron needed.
- The public website never starts a session.

## CSRF

All state-changing backend requests require the CSRF token (hidden form field)
or a `Sec-Fetch-Site: same-origin` request from a modern browser. The
`XSRF-TOKEN` cookie is disabled (not needed without JS HTTP clients).
`SameSite=Lax` adds defence in depth. GET requests never change state.

## HTTP security headers

Set by `App\Http\Middleware\SecurityHeaders` on every Laravel response:

| Header | Value |
|---|---|
| `Content-Security-Policy` | `default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; font-src 'self'; connect-src 'self'; media-src 'self'; worker-src 'self'; manifest-src 'self'; frame-src 'none'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'` (+ `upgrade-insecure-requests` in production) |
| `X-Content-Type-Options` | `nosniff` |
| `X-Frame-Options` | `DENY` (legacy; CSP `frame-ancestors` is authoritative) |
| `Referrer-Policy` | `strict-origin-when-cross-origin` (public), `same-origin` (backend) |
| `Permissions-Policy` | camera, microphone, geolocation, payment, USB, topics, … disabled |
| `Cross-Origin-Opener-Policy` / `-Resource-Policy` | `same-origin` |
| `Strict-Transport-Security` | `max-age=31536000` in production over HTTPS. `includeSubDomains` only after checking all subdomains (`HSTS_INCLUDE_SUBDOMAINS`). Preload is not enabled. |

Backend responses additionally (`AdminAreaHeaders`, also for 404s under the
prefix): `Cache-Control: no-store, private`, `Pragma: no-cache`,
`Expires: 0`, `X-Robots-Tag: noindex, nofollow, noarchive`.

**CSP rules for developers**

- No inline `<script>`, no inline event handlers (`onclick=`), no
  `style="…"` attributes, no `<style>` blocks. Put CSS in `resources/css`, JS
  in `resources/js` (bundled by Vite, served from `/build`).
- No external hosts. A future third-party integration needs an explicit
  decision (privacy review) and a deliberate CSP change in `config/security.php`.
- `data:` is allowed for images only (used for the MFA QR code).
- Development only: while `npm run dev` runs (`public/hot` exists and
  `APP_ENV=local`), the Vite dev-server origin and `'unsafe-inline'` for styles
  (HMR injects `<style>`) are added. Laravel's interactive debug page (only with
  `APP_DEBUG=true`, impossible in production) is served without CSP.

Static files served directly by Apache get `nosniff` and long-term caching for
hashed build assets via `public/.htaccess`.

## HTTPS and hosts

- Canonical origin: `https://www.gemeinde-merching.de` (`APP_URL`).
- `CanonicalUrlRedirect`: plain HTTP (`FORCE_HTTPS`, default on in
  production) and alias hosts listed in `REDIRECT_HOSTS` (e.g.
  `gemeinde-merching.de`) are redirected in a single hop – 301 for GET/HEAD,
  308 for other methods – to the scheme and host of `APP_URL` with path and
  query preserved. The target host never comes from the request (no open
  redirect). All generated URLs use `APP_URL` (`URL::forceRootUrl`).
- Trusted hosts (`TRUSTED_HOSTS`, default: host of `APP_URL`, plus the
  `REDIRECT_HOSTS`) – requests with any other `Host` header get HTTP 400. This protects absolute links, e.g. in
  password-reset mails, against Host-header injection.
- Trusted proxies: none by default (`TRUSTED_PROXIES`); only set if goneo puts
  a reverse proxy in front (verify with `deploy:check` / request headers).

## Errors and debug output

- Production forces `app.debug=false` even if `.env` says otherwise; error
  pages are generic (`resources/views/errors/*`), JSON errors only contain
  `"Server Error"`. No stack traces, SQL, paths or config values (tested).
- `zend.exception_ignore_args` is enabled outside local development and
  sensitive parameters are marked `#[\SensitiveParameter]`, so secrets do not
  appear in traces.
- `DB::prohibitDestructiveCommands()` blocks `migrate:fresh`, `db:wipe` etc.
  in production.

## Logging

- Production: `LOG_STACK=daily`, `LOG_LEVEL=warning`, 14 days retention, files
  in `storage/logs` (outside the web root).
- A Monolog processor (`App\Logging\RedactSensitiveData`) replaces values of
  context keys containing password, token, secret, code, recovery, cookie,
  session, authorization, api key, credential with `[redacted]`.
- Never log: passwords, reset tokens, session IDs, cookies, authorization
  headers, MFA secrets/codes, recovery codes, full form submissions, uploaded
  documents. Avoid personal data in log messages.
- Audit log (`audit_events`, `App\Services\Audit\AuditLogger`): actor, action,
  affected record, timestamp, safe metadata. **No IP addresses, user agents or
  attempted e-mail addresses.** Currently recorded: `auth.login`,
  `auth.login_failed` (only for existing accounts), `auth.logout`,
  `auth.mfa_enabled`, `auth.mfa_failed`, `auth.recovery_code_used`,
  `auth.recovery_codes_regenerated`, `auth.mfa_reset`, `auth.password_reset`,
  `user.created`. Audit events are immutable (update throws).
- Audit retention: `AUDIT_RETENTION_DAYS`, default **730 days (provisional –
  must be confirmed with the Datenschutzbeauftragte before launch)**; `0`
  disables deletion. Expired events are deleted without cron: opportunistically
  when new events are written (lottery, batches of 1000), on every release
  activation and via `php artisan audit:prune` / `model:prune`. This rule
  covers only audit events – future content revisions get their own retention
  rules.

## Content safety

- Editor rich text is Markdown rendered by `SafeMarkdown`: raw HTML is escaped,
  `javascript:`/`data:`/`vbscript:`/`file:` links are removed, images are not
  rendered (no external requests). Being authenticated does not make content
  trusted. A controlled block editor will replace it (see content-model.md).
- All links entered by editors are validated by `SafeUrl` (http/https, no
  credentials or control characters).
- Tested with script tags, event-handler attributes, unsafe schemes and
  tracking images.

## Contact-form recipients

`ContactRoute::recipients` are internal addresses: encrypted at rest with
`APP_KEY` (`encrypted:array` cast), `$hidden` from serialisation, exposed to
public code only via `publicData()` (id, label, explanation), never in
revisions or audit metadata, redacted from log context (`recipient` key), and
only rendered in the backend form for users with `contact-route.edit`
(read-only views omit the field). Covered by `ContactRoutePrivacyTest`.

## Uploads

Implemented for documents (images, galleries and media follow)
(`config/uploads.php`, `App\Services\Uploads\UploadInspector`):

1. Size limit (`UPLOADS_MAX_KILOBYTES`, default 20 MB; PHP's
   `upload_max_filesize`/`post_max_size` must match).
2. Extension allowlist: pdf, jpg/jpeg, png, webp, docx, xlsx, pptx, odt, ods.
   **Not allowed:** SVG (can contain script), HTML, PHP and other executables,
   archives.
3. MIME type detected from the **file content** (libmagic/`finfo`) must match
   the extension; the client's filename and MIME type are never trusted.
4. Stored under a random name (`uploads/YYYY/MM/<40 random chars>.<ext>`) on the
   private disk `storage/app/private` – outside `public/`, never next to
   executable PHP files. The sanitised original filename is kept as metadata
   only (download name). SHA-256 is computed for integrity/duplicates.
5. Delivery only through controllers that check authorization/publication
   status (`DocumentStorage::response()`) with safe headers: `Content-Type` from
   the stored (detected) MIME type, `X-Content-Type-Options: nosniff`,
   `Content-Disposition: attachment` (`inline` only for PDFs/images) with an
   RFC 6266 encoded filename, and a sandboxing `Content-Security-Policy`.
   Physical files are only deleted when their document is permanently deleted
   and nothing references it. Public, published media may later be copied to a deliberately
   designed public media directory with PHP execution disabled
   (`public/.htaccess` already denies executing anything but `index.php`).
6. Images will be re-encoded (GD) on upload to strip metadata (EXIF/GPS) and
   neutralise polyglot files.

The automatic file-serving route of Laravel's `local` disk is disabled
(`'serve' => false`).

## Secrets and configuration

- `.env` is never committed (`.gitignore`), only `.env.example` without
  values. Production `.env` lives on the server outside the web root,
  permissions `600`.
- `APP_KEY` encrypts sessions and MFA secrets and keys the recovery-code HMAC –
  rotate only with `APP_PREVIOUS_KEYS` and a plan; back it up offline.
- No default credentials anywhere; seeders create no accounts.

## Dependency security

- `composer audit` and `npm audit` (also part of `composer check`).
- Lock files are committed; production installs exactly the locked versions.
- Keep Laravel on a supported release; check security advisories monthly and
  before every deployment.

## Web server hardening (`public/.htaccess`, root `.htaccess`)

- Document root must be `public/`. A root `.htaccess` denies everything in
  case the document root is ever misconfigured to the project root.
- Directory listings off, hidden files (`.env`, `.git`) blocked, only
  `index.php` may execute PHP.
