# Deployment to goneo (Webhosting Profi)

Status: **plan for a future deployment – nothing has been deployed yet.**
Items marked _(verify)_ are assumptions about the goneo account that must be
checked once SSH access is set up.

Runtime in production:

```
Apache (.htaccess) → PHP 8.4 → Laravel → MySQL
```

No Docker, no Node.js, no Redis, no queue worker, no root access needed.

## 1. Target layout on the server

Only `public/` may be reachable over HTTP. Everything else lives outside the
document root:

```
~/merching/                     (example path, not web-accessible)
├── app/ bootstrap/ config/ database/ lang/ resources/ routes/ vendor/ …
├── .env                        (chmod 600, never in Git)
├── storage/                    (logs, sessions cache files, private uploads)
└── public/                     ← domain document root (www.merching.de)
    ├── index.php
    ├── .htaccess
    └── build/                  (compiled CSS/JS, uploaded from local build)
```

In the goneo customer centre, point the domain to `~/merching/public`
_(verify how goneo maps a domain to a sub-directory)_. If the document root
can only be a fixed directory, place the application next to it and make that
directory contain only the contents of `public/` with `index.php` adjusted to
the application path – never upload the whole project into the web root.
A root-level `.htaccess` with `Require all denied` is shipped as a safety net.

Select **PHP 8.4** for the domain in the customer centre _(verify: per domain
or per directory)_ and make sure the SSH CLI uses 8.4 too
(`php -v`; goneo may provide versioned binaries such as `php84` or
`/usr/bin/php8.4` – _(verify)_; use that binary for all commands below).

Required PHP extensions: `pdo_mysql`, `mbstring`, `openssl`, `intl`,
`fileinfo`, `tokenizer`, `xml`, `ctype`, `json`, `bcmath`, `gd` (images,
later). Check with `php -m`.

Recommended PHP settings (php.ini / goneo settings): `expose_php=Off`,
`display_errors=Off`, `log_errors=On`, `zend.exception_ignore_args=On`,
`upload_max_filesize=32M`, `post_max_size=34M`, `memory_limit=256M`,
OPcache enabled.

## 2. One-time setup

1. Create the MySQL database and a user with privileges only on that database
   (goneo customer centre). Note host, port, name, user, password.
2. Upload the application (see step 4 of the regular deployment).
3. Create `~/merching/.env` from `.env.example` (production values below),
   `chmod 600 .env`.
4. `php artisan key:generate` – **back up the generated `APP_KEY` offline
   immediately** (it encrypts MFA secrets; see backups.md).
5. `php artisan migrate --force`
6. `php artisan permissions:sync`
7. `php artisan admin:create` (interactive; enter name, e-mail, password).
8. Log in at `https://www.merching.de/verwaltung/login`, set up the
   authenticator app, store the recovery codes safely.
9. Run the verification checklist (section 4).

### Production `.env` (values only on the server)

```dotenv
APP_NAME="Gemeinde Merching"
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:…                # generated on the server, backed up offline
APP_URL=https://www.merching.de # canonical host
APP_LOCALE=de
APP_LOCAL_TIMEZONE=Europe/Berlin

PUBLIC_INDEXING=false            # set to true only at go-live
ADMIN_PATH=verwaltung
MFA_REQUIRED=true
TRUSTED_HOSTS=www.merching.de    # add further hosts if they serve the app
# TRUSTED_PROXIES=               # only if goneo uses a reverse proxy (verify)
# HSTS_INCLUDE_SUBDOMAINS=false  # only after checking all subdomains

LOG_CHANNEL=stack
LOG_STACK=daily
LOG_LEVEL=warning
LOG_DAILY_DAYS=14

DB_CONNECTION=mysql
DB_HOST=…  DB_PORT=3306  DB_DATABASE=…  DB_USERNAME=…  DB_PASSWORD=…

SESSION_DRIVER=database
SESSION_LIFETIME=60
SESSION_ENCRYPT=true
CACHE_STORE=database
QUEUE_CONNECTION=sync
QUEUE_FAILED_DRIVER=null

MAIL_MAILER=smtp
MAIL_SCHEME=smtps                # or smtp + STARTTLS on 587 (verify goneo)
MAIL_HOST=…  MAIL_PORT=465  MAIL_USERNAME=…  MAIL_PASSWORD=…
MAIL_FROM_ADDRESS=no-reply@merching.de
MAIL_FROM_NAME="Gemeinde Merching"
```

Make sure SPF/DKIM/DMARC for the sender domain are configured so password
reset mails are delivered.

## 3. Regular deployment

### 3.1 Build and test locally

```bash
git checkout main && git pull
docker compose up -d
docker compose exec app composer check     # pint, larastan, phpunit, composer audit
npm ci && npm run audit && npm run build && npm test
```

Deploy only a clean, committed, tested state. Note the commit hash.

### 3.2 Compile frontend assets locally

`npm run build` creates `public/build/` (manifest + hashed files). These files
are uploaded; goneo never needs Node.js.

### 3.3 Back up production first

Always back up database and uploads before deploying (see
[backups.md](backups.md#backup-before-deployment)).

### 3.4 Put the site into maintenance mode

```bash
ssh goneo 'cd ~/merching && php artisan down --retry=60'
```

### 3.5 Upload code

Upload the release (without `.env`, `storage/`, `node_modules/`, tests and dev
files), e.g. with rsync over SSH:

```bash
rsync -az --delete \
  --exclude='.git' --exclude='.env' --exclude='storage/' --exclude='node_modules/' \
  --exclude='vendor/' --exclude='tests/' --exclude='docker/' --exclude='designsystem-inspiration/' \
  --exclude='public/hot' \
  ./ goneo:~/merching/
```

`--delete` removes files that no longer exist in the release; the excludes
protect server-only data (`.env`, `storage/`). Alternatively use `git archive`
of the tagged commit plus the `public/build` directory.

### 3.6 Install production Composer dependencies

Option A – on the server (Composer available or `composer.phar` uploaded
_(verify)_):

```bash
php composer.phar install --no-dev --classmap-authoritative --no-interaction --prefer-dist
```

Option B – build `vendor/` locally with the identical PHP version and upload it:

```bash
docker compose exec app composer install --no-dev --classmap-authoritative --no-interaction
rsync -az --delete vendor/ goneo:~/merching/vendor/
docker compose exec app composer install   # restore dev dependencies locally
```

### 3.7 Configure `.env`

Only when configuration changes. Compare with `.env.example` for new keys.

### 3.8 Run migrations and sync permissions

```bash
php artisan migrate --force
php artisan permissions:sync
```

Migrations are the only way the schema changes. Destructive commands
(`migrate:fresh`, `db:wipe`) are blocked in production.

### 3.9 Rebuild caches

```bash
php artisan optimize:clear
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache
```

After `config:cache`, `.env` is no longer read at runtime – re-run it after
every `.env` change.

### 3.10 Storage permissions

```bash
chmod -R u+rwX storage bootstrap/cache
chmod 600 .env
find . -path ./storage -prune -o -type f -name '*.php' -perm /o+w -print   # should print nothing
```

On goneo PHP runs as the account user, so world-writable permissions (777) are
never needed.

### 3.11 Leave maintenance mode

```bash
php artisan up
```

## 4. Verification after every deployment

```bash
php artisan deploy:check     # fails if any production setting is unsafe
```

Then manually:

1. **HTTPS**: `http://www.merching.de/` redirects (301) to `https://…`;
   certificate valid; `Strict-Transport-Security` present.
2. **`APP_DEBUG=false`**: `grep APP_DEBUG .env`; open a non-existent URL → the
   generic 404 page without technical details.
3. **Admin login**: log in at `/verwaltung/login` with password + authenticator
   code; logout works; `/verwaltung/dashboard` without login redirects to the
   login page.
4. **Public smoke test**: start page loads, CSS is applied.
5. **No third-party requests**: browser devtools → Network: all requests go to
   the own domain; Application → Cookies: no cookie on public pages.
6. **Robots/indexing**: `curl -sI https://www.merching.de/ | grep -i x-robots`
   and `/robots.txt` match the intended `PUBLIC_INDEXING` state; backend always
   `noindex`.
7. **Security headers**: `curl -sI https://www.merching.de/` and
   `…/verwaltung/login` show CSP, `X-Content-Type-Options`, `X-Frame-Options`,
   `Referrer-Policy`, `Permissions-Policy`, HSTS; backend `Cache-Control:
   no-store`. Optionally check with an external header scanner.
8. **Mail**: request a password reset for a test account and check delivery.
9. **Logs**: `tail storage/logs/laravel-*.log` shows no new errors.

## 5. Rollback

1. `php artisan down`
2. Re-deploy the previous commit (same steps). If a migration must be undone,
   prefer a new forward migration; restore the database backup only if data was
   damaged.
3. `php artisan optimize:clear && php artisan config:cache …`, `php artisan up`.

## 6. Scheduled tasks

Nothing requires cron today: publication windows are evaluated at request time,
sessions/cache expire via lottery and TTL. Future housekeeping (e.g. audit-log
retention, sitemap pre-generation) can be triggered by goneo's WebCron calling
a protected endpoint or by `php artisan schedule:run` if SSH cron becomes
available. No persistent worker will ever be required.

## Known considerations

- goneo's MySQL version (MySQL 8.x vs. MariaDB) _(verify)_; migrations use
  portable types and `utf8mb4_unicode_ci`.
- Whether a reverse proxy/CDN sits in front (affects `TRUSTED_PROXIES`,
  `isSecure()` detection and HSTS) _(verify)_.
- Outgoing SMTP host/port/encryption of the goneo mailbox _(verify)_.
- `defer()` sends reset mails after the response; on PHP-FPM via
  `fastcgi_finish_request()`, otherwise at the end of the request – both work
  without a worker.
