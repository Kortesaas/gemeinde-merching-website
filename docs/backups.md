# Backups

The application code is recoverable from Git (plus `npm run build` and
`composer install`). What must be backed up separately:

1. **MySQL database** – content, accounts, sessions, audit log.
2. **Uploaded files/media** – `~/merching/shared/storage/app/private/`
   (and later any public media directory).
3. **Configuration outside Git** – `~/merching/shared/.env`, above all
   `APP_KEY`.

Release artifacts (`~/merching/releases/`) need no backup: they can be rebuilt
from Git with `scripts/release/build.sh <commit>` (the commit is recorded in
each release's `RELEASE` file).

> **`APP_KEY` is critical.** It encrypts the MFA secrets and session data and
> keys the recovery-code hashes. A database backup restored with a different
> `APP_KEY` leaves every account unable to use MFA (would require
> `admin:reset-mfa` for everyone). Store `APP_KEY` (ideally the whole `.env`)
> in the municipality's password manager or another encrypted offline location.

## Principles

- **3-2-1**: at least three copies, two different media, **at least one copy
  outside the goneo account** (e.g. the municipality's own NAS/server or an
  encrypted storage in Germany/EU). goneo's own backups are a useful extra, not
  the only backup.
- Backups contain personal data → encrypt them at rest and in transit,
  restrict access, delete according to the retention plan.
- No credentials in scripts or in Git. Use a MySQL option file (`~/.my.cnf`,
  `chmod 600`) on the server and SSH keys for transfers.
- A backup only counts if a restore has been tested.

## Database

On the server (via SSH), credentials from `~/.my.cnf`:

```ini
# ~/.my.cnf  (chmod 600, never in Git)
[mysqldump]
host=…
user=…
password=…
```

```bash
mkdir -p ~/backups && chmod 700 ~/backups
mysqldump --defaults-extra-file=~/.my.cnf --single-transaction --quick \
  --routines --triggers --no-tablespaces --default-character-set=utf8mb4 \
  DATABASE_NAME | gzip > ~/backups/db-$(date +%Y%m%d-%H%M%S).sql.gz
```

`--single-transaction` gives a consistent snapshot of InnoDB tables without
locking the site. Sessions and cache are included but harmless.

## Media / uploads

```bash
tar -czf ~/backups/media-$(date +%Y%m%d-%H%M%S).tar.gz -C ~/merching/shared storage/app/private
```

or incremental (more efficient for large media):

```bash
rsync -az --delete goneo:~/merching/shared/storage/app/private/ /backup/merching/media/
```

## Off-site copy

Pull the backups from a machine outside goneo (pull is safer than push: a
compromised web account cannot delete the off-site copies):

```bash
rsync -az goneo:~/backups/ /backup/merching/db/
```

Encrypt at rest (e.g. encrypted volume or `age`/`gpg`). Then remove old local
dumps on the server to limit exposure.

## Schedule and retention (proposal – to be confirmed)

| What | When | Keep |
|---|---|---|
| Database | daily | 14 daily, 8 weekly, 12 monthly |
| Media | daily (incremental) | 30 days of versions |
| `.env` / `APP_KEY` | after every change | current + previous |
| Before deployment | always | until the next successful deployment |

Automation: goneo WebCron is limited; a cron job on the off-site machine that
SSHes in, runs the dump and pulls the files is the most robust option.

## Backup before deployment

Every deployment (see deployment-goneo.md section 4) starts with:

```bash
ssh goneo 'cd ~ && mysqldump --defaults-extra-file=~/.my.cnf --single-transaction --quick --no-tablespaces DATABASE_NAME | gzip > backups/predeploy-$(date +%Y%m%d-%H%M%S).sql.gz'
ssh goneo 'tar -czf ~/backups/predeploy-media-$(date +%Y%m%d-%H%M%S).tar.gz -C ~/merching/shared storage/app/private'
```

## Restore

1. `php artisan down`
2. Database:
   ```bash
   gunzip -c db-YYYYMMDD-HHMMSS.sql.gz | mysql --defaults-extra-file=~/.my.cnf DATABASE_NAME
   ```
3. Media: extract/rsync back to `~/merching/shared/storage/app/private/`.
4. Ensure `~/merching/shared/.env` with the **original `APP_KEY`** is in place.
5. Activate a release matching the backup (or newer) with
   `scripts/release/activate.sh`; it applies newer migrations and rebuilds the
   caches. If the matching release was already removed, rebuild it locally with
   `scripts/release/build.sh <commit>`.
6. `php ~/merching/current/artisan up`, then run the verification checklist of
   the deployment guide.

Test a full restore into a separate (local or staging) database at least
twice a year and after major changes; document the result.
