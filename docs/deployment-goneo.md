# Deployment to goneo (Webhosting Profi)

Status: **plan for a future deployment – nothing has been deployed yet.**
Items marked _(verify)_ are assumptions about the goneo account that must be
checked once SSH access is set up.

Runtime in production:

```
Apache (.htaccess) → PHP 8.4 → Laravel → MySQL
```

No Docker, no Node.js, no Composer, no Redis, no queue worker and no root
access are needed on the server. Production needs only Apache, the PHP 8.4
CLI/web SAPI and MySQL.

Canonical address: **`https://www.gemeinde-merching.de`** – the existing host
and existing paths are preserved. The non-www host is redirected to it (single
301, path and query kept) when listed in `REDIRECT_HOSTS`.

## Overview

```
local machine (Linux PHP 8.4 dev image + Node container)      goneo (SSH, PHP CLI only)
──────────────────────────────────────────────────────        ─────────────────────────────
1. test & commit                                              4. back up DB + media
2. scripts/release/build.sh  → build/releases/<name>.tar.gz   5. upload + verify checksum
   (vendor/ --no-dev, public/build/, no dev files)            6. extract to releases/<name>
3. checksum (.sha256)                                         7. scripts/release/activate.sh
                                                              8. verification checklist
```

The release artifact is self-contained: application code, production
`vendor/` (installed with `--no-dev --classmap-authoritative` in the Linux
PHP 8.4 image) and compiled assets in `public/build/`. It contains no `.env`,
no storage data, no tests, no Docker files, no frontend sources and never
`designsystem-inspiration/`.

## 1. Server layout

```
~/merching/                          (example; NOT web-accessible)
├── releases/
│   ├── merching-20261101-090000-<commit>/   one directory per release
│   └── merching-20261115-090000-<commit>/
├── shared/
│   ├── .env                         production configuration (chmod 600)
│   └── storage/                     logs, private uploads, framework files (chmod 700)
└── current -> releases/merching-20261115-090000-<commit>
```

Each release links `storage` → `shared/storage` and `.env` → `shared/.env`;
`activate.sh` sets this up. Switching releases is an atomic change of the
`current` symlink.

- In the goneo customer centre, set the document root of
  `www.gemeinde-merching.de` to `~/merching/current/public` _(verify how goneo
  maps a domain to a sub-directory and that a symlinked path is accepted)_.
  Only `public/` is reachable over HTTP; `.env`, code, `vendor/`, `storage/`,
  logs, uploads and backups are outside the document root. A root
  `.htaccess` with `Require all denied` is a safety net if the document root is
  ever misconfigured.
- If goneo does not accept a symlinked document root: point the domain to a
  fixed directory `~/merching/app/public` and, instead of switching the
  symlink, `rsync -a --delete` the extracted release into `~/merching/app/`
  (excluding `.env` and `storage`) during the maintenance window.
- The non-www domain `gemeinde-merching.de` should point to the same document
  root and have a valid certificate; the application then redirects it
  (`REDIRECT_HOSTS`). Alternatively configure the redirect in the goneo
  customer centre – either way the result must be one 301 to
  `https://www.gemeinde-merching.de` + original path.
- Select **PHP 8.4** for the domain _(verify: per domain or per directory)_.
  The SSH CLI must also be 8.4: check `php -v`; goneo may provide versioned
  binaries such as `php84` or `/usr/bin/php8.4` _(verify)_. Pass it as
  `PHP_BIN=…` to `activate.sh` and use it for every `artisan` call.
- Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `intl`,
  `fileinfo`, `tokenizer`, `xml`, `ctype`, `bcmath`, `gd`. The build checks
  them for the build image (`composer check-platform-reqs`); check the server
  with `php -m`.
- Recommended PHP settings: `expose_php=Off`, `display_errors=Off`,
  `log_errors=On`, `zend.exception_ignore_args=On`,
  `upload_max_filesize=32M`, `post_max_size=34M`, `memory_limit=256M`, OPcache
  on.

### Production `.env` (`~/merching/shared/.env`, values only on the server)

```dotenv
APP_NAME="Gemeinde Merching"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:…                         # generated once, backed up offline
APP_URL=https://www.gemeinde-merching.de # canonical origin
APP_LOCALE=de
SITE_TIMEZONE=Europe/Berlin

PUBLIC_INDEXING=false                    # true only at go-live
ADMIN_PATH=verwaltung
MFA_REQUIRED=true
TRUSTED_HOSTS=www.gemeinde-merching.de
REDIRECT_HOSTS=gemeinde-merching.de      # non-www -> www (301, path kept)
# TRUSTED_PROXIES=                       # only if goneo uses a reverse proxy (verify)
# HSTS_INCLUDE_SUBDOMAINS=false          # only after checking all subdomains

AUDIT_RETENTION_DAYS=730                 # provisional – confirm with the Datenschutzbeauftragte

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

DB_CONNECTION=mysql
DB_HOST=…
DB_PORT=3306
DB_DATABASE=…
DB_USERNAME=…
DB_PASSWORD=…

SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
QUEUE_FAILED_DRIVER=null

MAIL_MAILER=smtp
MAIL_SCHEME=smtps                        # or smtp + STARTTLS on 587 (verify)
MAIL_HOST=…
MAIL_PORT=465
MAIL_USERNAME=…
MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=…@gemeinde-merching.de # address to be confirmed
MAIL_FROM_NAME="Gemeinde Merching"
```

Configure SPF/DKIM/DMARC for the sender domain so password-reset mails arrive.

## 2. Build the release (locally)

```bash
git switch main && git pull
docker compose up -d
docker compose exec app composer check         # Pint, Larastan, PHPUnit, composer audit
npm ci && npm run audit && npm run build && npm test
scripts/release/build.sh                        # or: scripts/release/build.sh <tag|commit>
```

`build.sh` refuses to run on a dirty working tree, exports exactly the
committed state (`git archive`), installs production dependencies inside the
Linux PHP 8.4 image, compiles assets in a Linux Node container, removes
development files, runs sanity checks (no dev packages, no `.env`, no
`public/hot`) and writes:

```
build/releases/merching-<UTC timestamp>-<commit>.tar.gz
build/releases/merching-<UTC timestamp>-<commit>.tar.gz.sha256
```

The file `RELEASE` inside the artifact records release name, commit, build
time, PHP and Node versions.

## 3. First installation (one time)

1. Create the MySQL database and a user with privileges only on that
   database (goneo customer centre).
2. On the server:
   ```bash
   mkdir -p ~/merching/releases ~/merching/shared && chmod 700 ~/merching/shared
   ```
3. Upload, verify and extract the first release (section 4, step 2).
4. Create `~/merching/shared/.env` from the template above, `chmod 600`.
   Generate the key with the extracted release and paste it into `APP_KEY`:
   ```bash
   php ~/merching/releases/<name>/artisan key:generate --show
   ```
   **Back up `APP_KEY` offline immediately** (it encrypts MFA secrets; see
   backups.md).
5. Activate the release (section 4, step 3).
6. Create the first administrator interactively (password is prompted, never
   passed as an argument):
   ```bash
   php ~/merching/current/artisan admin:create
   ```
7. Log in at `https://www.gemeinde-merching.de/verwaltung/login`, set up the
   authenticator app, store the recovery codes safely.
8. Run the verification checklist (section 5).

## 4. Regular deployment

1. **Back up first** – database and uploads
   ([backups.md](backups.md#backup-before-deployment)).
2. **Upload and verify:**
   ```bash
   scp build/releases/merching-…tar.gz build/releases/merching-…tar.gz.sha256 goneo:~/merching/releases/
   ssh goneo
   cd ~/merching/releases && sha256sum -c merching-….tar.gz.sha256
   tar -xzf merching-….tar.gz && rm merching-….tar.gz merching-….tar.gz.sha256
   ```
3. **Activate:**
   ```bash
   PHP_BIN=php ~/merching/releases/merching-…/scripts/release/activate.sh
   ```
   The script
   - links `shared/.env` and `shared/storage` into the release;
   - clears file caches and runs `php artisan deploy:check` **before changing
     anything** – if a production setting is unsafe it aborts and the live
     site is untouched;
   - enables maintenance mode, runs `migrate --force` and `permissions:sync`;
   - builds `config`, `route`, `view` and `event` caches;
   - atomically switches `current` to the new release, disables maintenance
     mode, prunes expired audit events;
   - deletes old releases, keeping the newest five (`KEEP_RELEASES`).

   If a step fails after maintenance mode was enabled, the script says so;
   `current` still points to the previous release. Investigate, then either fix
   and re-run or roll back (section 6).
4. Run the verification checklist (section 5).

Migrations are the only way the schema changes; destructive commands
(`migrate:fresh`, `db:wipe`) are blocked in production. After any later
`.env` change run `php current/artisan config:cache` again (the cached config
does not read `.env`).

PHP's realpath cache may keep serving the previous release for up to
`realpath_cache_ttl` seconds (default 120) after the switch _(verify on
goneo)_. This is harmless as long as migrations are backward-compatible with
the previous release (expand/contract), which is the rule for this project.

## 5. Verification after every deployment

`activate.sh` already ran `deploy:check`. Then check manually:

1. **HTTPS and canonical host:**
   `curl -sI http://www.gemeinde-merching.de/aktuelles` → `301` to
   `https://www.gemeinde-merching.de/aktuelles`;
   `curl -sI https://gemeinde-merching.de/aktuelles?x=1` → `301` to
   `https://www.gemeinde-merching.de/aktuelles?x=1` (one hop);
   certificate valid for both hosts; `Strict-Transport-Security` present;
   `curl -sI https://www.gemeinde-merching.de/aktuelles/?x=1` → one `301` to
   `https://www.gemeinde-merching.de/aktuelles?x=1` (canonical URLs are
   slashless).
2. **`APP_DEBUG=false`:** `grep APP_DEBUG ~/merching/shared/.env`; a
   non-existent URL shows the generic 404 page without technical details.
3. **Admin login:** log in with password + authenticator code; logout works;
   `/verwaltung/dashboard` without login redirects to the login page.
4. **Public smoke test:** start page loads, CSS applied, `cat current/RELEASE`
   shows the expected commit.
5. **No third-party requests:** browser devtools → Network: only requests to
   `www.gemeinde-merching.de`; Application → Cookies: none on public pages.
6. **Robots/indexing:** `curl -sI https://www.gemeinde-merching.de/ | grep -i x-robots`
   and `/robots.txt` match the intended `PUBLIC_INDEXING` state; the backend is
   always `noindex`.
7. **Security headers:** `curl -sI https://www.gemeinde-merching.de/` and
   `…/verwaltung/login`: CSP, `X-Content-Type-Options`, `X-Frame-Options`,
   `Referrer-Policy`, `Permissions-Policy`, HSTS; backend `Cache-Control:
   no-store`; session cookie `__Host-merching_session; secure; httponly;
   samesite=lax`.
8. **Mail:** request a password reset for a test account; check delivery.
9. **Logs:** `ls -t ~/merching/shared/storage/logs | head` – no new errors.

## 6. Rollback

Switch `current` back to the previous release (its caches are still in
place):

```bash
cd ~/merching
ln -sfn releases/<previous-release> current.next && mv -Tf current.next current
php current/artisan up
```

If the failed release ran a migration that the previous release cannot work
with, either deploy a fixed release with a forward migration or restore the
pre-deployment database backup.

## 7. Scheduled tasks

Nothing requires cron: publication windows are evaluated at request time,
sessions/cache expire via lottery and TTL, and expired audit events are pruned
opportunistically (and on every activation). Future housekeeping can use
goneo's WebCron calling a protected endpoint. No persistent worker will ever
be required.

## Known considerations _(verify)_

- MySQL vs. MariaDB and version on goneo (migrations use portable types and
  `utf8mb4_unicode_ci`).
- Symlinked document root support; otherwise use the rsync variant above.
- Reverse proxy/CDN in front (affects `TRUSTED_PROXIES` and HTTPS detection).
- SMTP host/port/encryption of the goneo mailbox.
- Versioned PHP CLI binary name.
- GNU `mv -T` is used for the atomic switch (standard on Linux hosting).
