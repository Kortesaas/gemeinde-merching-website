#!/usr/bin/env bash
#
# Builds a self-contained release artifact for goneo (no Composer and no Node.js
# needed on the server). Run locally, from a clean working tree:
#
#   scripts/release/build.sh            # builds HEAD
#   scripts/release/build.sh v1.2.0     # builds a tag/commit
#
# PHP dependencies are installed inside the Linux PHP 8.4 development image
# (same PHP version and platform as production), frontend assets in a Linux
# Node container. Output: build/releases/<name>.tar.gz (+ .sha256).
# See docs/deployment-goneo.md.

set -euo pipefail

ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$ROOT"

REF="${1:-HEAD}"
PHP_IMAGE="${PHP_IMAGE:-merching-php-dev}"
NODE_IMAGE="${NODE_IMAGE:-node:22.23.1-bookworm-slim}"

if [[ "$REF" == "HEAD" && -n "$(git status --porcelain)" ]]; then
    echo "error: working tree is not clean – commit first, a release must match a commit." >&2
    exit 1
fi

COMMIT="$(git rev-parse --verify "${REF}^{commit}")"
NAME="merching-$(date -u +%Y%m%d-%H%M%S)-${COMMIT:0:12}"
WORK_ROOT="$ROOT/build/work"
WORK="$WORK_ROOT/$NAME"
OUT="$ROOT/build/releases"

if ! docker image inspect "$PHP_IMAGE" >/dev/null 2>&1; then
    docker compose build app
fi

rm -rf "$WORK"
mkdir -p "$WORK" "$OUT"

echo "==> Exporting commit $COMMIT"
git archive "$COMMIT" | tar -x -C "$WORK"

run() {
    local image="$1"
    shift
    docker run --rm --user "$(id -u):$(id -g)" \
        -e HOME=/tmp -e COMPOSER_HOME=/tmp/composer -e npm_config_cache=/tmp/npm \
        -v "$WORK:/release" -w /release "$image" "$@"
}

PHP_VERSION="$(run "$PHP_IMAGE" php -r 'echo PHP_VERSION;')"
echo "==> PHP dependencies (production only, PHP $PHP_VERSION)"
run "$PHP_IMAGE" composer install --no-dev --classmap-authoritative --no-interaction --prefer-dist --no-progress
run "$PHP_IMAGE" composer check-platform-reqs --no-dev

NODE_VERSION="$(run "$NODE_IMAGE" node --version)"
echo "==> Frontend assets (Node $NODE_VERSION)"
run "$NODE_IMAGE" sh -c 'npm ci --no-audit --no-fund && npm run build'

echo "==> Removing build-only and development files"
rm -rf "$WORK"/{node_modules,tests,docker,docs,.github} \
    "$WORK"/resources/{css,js} \
    "$WORK"/scripts/release/build.sh \
    "$WORK"/{compose.yaml,phpunit.xml,phpstan.neon,pint.json,playwright.config.js,vite.config.js} \
    "$WORK"/{package.json,package-lock.json,.editorconfig,.gitattributes,.npmrc,README.md} \
    "$WORK"/database/demo "$WORK"/database/seeders/Demo "$WORK"/database/seeders/DevelopmentDemoSeeder.php

cat > "$WORK/RELEASE" <<META
release=$NAME
commit=$COMMIT
built_at=$(date -u +%Y-%m-%dT%H:%M:%SZ)
php=$PHP_VERSION
node=$NODE_VERSION
META

echo "==> Sanity checks"
test -f "$WORK/vendor/autoload.php"
test -f "$WORK/public/build/manifest.json"
test ! -e "$WORK/.env"
test ! -e "$WORK/vendor/phpunit" || { echo "error: dev dependencies in vendor/" >&2; exit 1; }
test ! -e "$WORK/public/hot" || { echo "error: public/hot must not be released" >&2; exit 1; }
test ! -e "$WORK/designsystem-inspiration"

tar -czf "$OUT/$NAME.tar.gz" -C "$WORK_ROOT" "$NAME"
(
    cd "$OUT"
    if command -v sha256sum >/dev/null; then sha256sum "$NAME.tar.gz"; else shasum -a 256 "$NAME.tar.gz"; fi > "$NAME.tar.gz.sha256"
)
rm -rf "$WORK"

echo
echo "Release artifact: build/releases/$NAME.tar.gz"
echo "Checksum:         build/releases/$NAME.tar.gz.sha256"
