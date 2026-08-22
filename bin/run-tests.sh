#!/usr/bin/env bash
# Run canonical Espo test suite (PHPStan + PHPUnit).
# Integration tests use db_test via tests/integration/config.php (not the dev/prod DB).
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

run() {
    if [[ "$IN_DDEV" -eq 1 ]] || [[ -n "${GITHUB_ACTIONS:-}" ]]; then
        "$@"
    elif command -v ddev >/dev/null 2>&1 && ddev describe >/dev/null 2>&1; then
        ddev exec "$@"
    else
        "$@"
    fi
}

run_local() {
    if [[ "$IN_DDEV" -eq 1 ]] || [[ -n "${GITHUB_ACTIONS:-}" ]]; then
        "$@"
    elif command -v ddev >/dev/null 2>&1 && ddev describe >/dev/null 2>&1; then
        ddev exec "$@"
    else
        "$@"
    fi
}

VERSION="$(read_espo_version)"
BUILD_DIR="build/EspoCRM-${VERSION}"

if [[ ! -f "${BUILD_DIR}/bootstrap.php" ]]; then
    echo "Missing ${BUILD_DIR} — running bin/test-build.sh (grunt test equivalent)..."
    run_local bash bin/test-build.sh
fi

if [[ ! -f vendor/bin/phpunit ]]; then
    echo "Installing composer dependencies..."
    run_local composer install --no-interaction --no-progress
fi

run vendor/bin/phpstan analyse -c phpstan.neon
run vendor/bin/phpunit tests/unit "$@"
run vendor/bin/phpunit tests/integration "$@"
