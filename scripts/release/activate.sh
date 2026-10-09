#!/usr/bin/env bash
#
# Activates an uploaded and extracted release ON THE SERVER (goneo, via SSH).
# Needs only the PHP 8.4 CLI – no Composer, no Node.js.
#
#   ~/merching/releases/<name>/scripts/release/activate.sh
#
# Layout (BASE defaults to the directory above releases/):
#   BASE/releases/<name>/   extracted release artifacts
#   BASE/shared/.env        production configuration (chmod 600)
#   BASE/shared/storage/    persistent storage (logs, uploads, sessions files …)
#   BASE/current -> releases/<name>   (domain document root: BASE/current/public)
#
# Environment: PHP_BIN (default "php"), KEEP_RELEASES (default 5).
# See docs/deployment-goneo.md.

# -E: the ERR trap also fires inside functions.
set -Eeuo pipefail

RELEASE="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
BASE="${BASE:-$(cd "$RELEASE/../.." && pwd)}"
SHARED="$BASE/shared"
CURRENT="$BASE/current"
PHP_BIN="${PHP_BIN:-php}"
KEEP_RELEASES="${KEEP_RELEASES:-5}"
NAME="$(basename "$RELEASE")"

artisan() { "$PHP_BIN" "$RELEASE/artisan" "$@"; }

trap 'echo >&2; echo "ACTIVATION FAILED. current still points to the previous release." >&2; \
      echo "If maintenance mode was enabled: investigate, then run \"$PHP_BIN $CURRENT/artisan up\" or restore the backup." >&2' ERR

echo "==> Activating $NAME in $BASE"
test -f "$RELEASE/RELEASE" || { echo "error: $RELEASE is not a release artifact" >&2; exit 1; }
test -f "$RELEASE/vendor/autoload.php" || { echo "error: vendor/ missing" >&2; exit 1; }
"$PHP_BIN" -r 'exit(version_compare(PHP_VERSION, "8.4.0", ">=") ? 0 : 1);' \
    || { echo "error: $PHP_BIN is not PHP >= 8.4 (set PHP_BIN)" >&2; exit 1; }
test -f "$SHARED/.env" || { echo "error: $SHARED/.env missing (see docs/deployment-goneo.md)" >&2; exit 1; }

echo "==> Linking shared .env and storage"
mkdir -p "$SHARED/storage/app/private" "$SHARED/storage/app/public" "$SHARED/storage/logs" \
    "$SHARED/storage/framework/cache/data" "$SHARED/storage/framework/sessions" \
    "$SHARED/storage/framework/views" "$SHARED/storage/framework/testing"
chmod 700 "$SHARED/storage"
chmod 600 "$SHARED/.env"
rm -rf "$RELEASE/storage"
ln -s "$SHARED/storage" "$RELEASE/storage"
ln -sfn "$SHARED/.env" "$RELEASE/.env"

echo "==> Verifying configuration (nothing has changed yet)"
# File caches only – the application cache lives in the database, whose
# tables do not exist yet on a first install.
artisan config:clear >/dev/null
artisan route:clear >/dev/null
artisan view:clear >/dev/null
artisan event:clear >/dev/null
artisan deploy:check

echo "==> Maintenance mode"
if [[ -e "$CURRENT/artisan" ]]; then
    "$PHP_BIN" "$CURRENT/artisan" down --retry=60 || true
fi

echo "==> Database migrations and permissions"
artisan migrate --force
artisan permissions:sync
artisan search:rebuild

echo "==> Caches"
artisan config:cache
artisan route:cache
artisan view:cache
artisan event:cache

echo "==> Switching current -> $NAME"
ln -sfn "releases/$NAME" "$BASE/current.next"
mv -Tf "$BASE/current.next" "$CURRENT"

artisan up
artisan audit:prune
artisan search:prune-statistics

echo "==> Removing old releases (keeping $KEEP_RELEASES)"
ls -1dt "$BASE"/releases/*/ | tail -n +"$((KEEP_RELEASES + 1))" | while read -r old; do
    [[ "$(cd "$old" && pwd)" == "$RELEASE" ]] || rm -rf "$old"
done

echo "Activated $NAME. Now run the manual verification checklist (docs/deployment-goneo.md)."
