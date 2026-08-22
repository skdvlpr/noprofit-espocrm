#!/usr/bin/env bash
# Prepare canonical Espo integration test build (grunt test equivalent).
# Creates build/EspoCRM-{version}/ snapshot and ensures db_test exists (DDEV only).
#
# @see https://docs.espocrm.com/development/tests/
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
cd "$ROOT_DIR"

# shellcheck source=lib/read-espo-version.sh
source "$ROOT_DIR/bin/lib/read-espo-version.sh"

if [[ -f /.dockerenv ]] || [[ -n "${DDEV_PROJECT:-}" ]]; then
    IN_DDEV=1
else
    IN_DDEV=0
fi

run_composer() {
    if [[ "$IN_DDEV" -eq 1 ]] || [[ -n "${GITHUB_ACTIONS:-}" ]]; then
        composer "$@"
    elif command -v ddev >/dev/null 2>&1 && ddev describe >/dev/null 2>&1; then
        ddev composer "$@"
    else
        composer "$@"
    fi
}

ensure_test_database() {
    if [[ -n "${GITHUB_ACTIONS:-}" ]]; then
        echo "CI: using GitHub Actions MySQL service (database from workflow env)."
        return 0
    fi

    local sql="
CREATE DATABASE IF NOT EXISTS db_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
GRANT ALL PRIVILEGES ON db_test.* TO 'db'@'%';
FLUSH PRIVILEGES;
"

    if [[ "$IN_DDEV" -eq 1 ]]; then
        mysql -uroot -proot -h db -e "$sql"
    elif command -v ddev >/dev/null 2>&1 && ddev describe >/dev/null 2>&1; then
        ddev mysql -e "$sql"
    else
        echo "ERROR: DDEV is required to provision db_test locally." >&2
        exit 1
    fi
}

VERSION="$(read_espo_version)"
BUILD_NAME="EspoCRM-${VERSION}"
BUILD_DIR="$ROOT_DIR/build/${BUILD_NAME}"

echo "Espo version: ${VERSION}"
echo "Building test snapshot: ${BUILD_DIR}"

mkdir -p "$ROOT_DIR/build"
rm -rf "$BUILD_DIR"

rsync -a \
    --exclude 'build/' \
    --exclude 'data/' \
    --exclude '.git/' \
    --exclude 'node_modules/' \
    --exclude '.phpunit.cache/' \
    --exclude '.ddev/' \
    "$ROOT_DIR/" "$BUILD_DIR/"

echo "Ensuring integration test database db_test exists..."
ensure_test_database

echo "Installing composer dependencies (including dev: phpunit, phpstan)..."
run_composer install --no-interaction --no-progress

TEST_INSTALL_DIR="$ROOT_DIR/build/test"
echo "Syncing integration test install: ${TEST_INSTALL_DIR}"
rm -rf "$TEST_INSTALL_DIR"
cp -r "$BUILD_DIR" "$TEST_INSTALL_DIR"
mkdir -p "$TEST_INSTALL_DIR/data/cache" "$TEST_INSTALL_DIR/data/upload" "$TEST_INSTALL_DIR/data/logs"
chmod -R a+rwX "$TEST_INSTALL_DIR/data" 2>/dev/null || true

APP_MTIME="$(stat -c %Y "${BUILD_DIR}/application" 2>/dev/null || stat -f %m "${BUILD_DIR}/application")"
CONFIG_PHP="$ROOT_DIR/tests/integration/config.php"
if [[ -f "$CONFIG_PHP" ]] && [[ -n "$APP_MTIME" ]]; then
    sed -i "s/'lastModifiedTime' => [0-9]*/'lastModifiedTime' => ${APP_MTIME}/" "$CONFIG_PHP"
fi

echo ""
echo "Done. Canonical commands:"
echo "  vendor/bin/phpunit tests/unit"
echo "  vendor/bin/phpunit tests/integration"
echo "  vendor/bin/phpstan analyse -c phpstan.neon"
echo "  bash bin/run-tests.sh"
