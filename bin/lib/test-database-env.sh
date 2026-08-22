#!/usr/bin/env bash
# Export TEST_DATABASE_* for Espo integration tests.
#
# Recognized environments:
#   - GitHub Actions: workflow must define TEST_DATABASE_* (job env).
#   - DDEV web container: IS_DDEV_PROJECT=true → isolated db_test on the DDEV db service.
#
# Fails closed everywhere else — no silent host fallbacks in PHP.
set -euo pipefail

if [[ -n "${TEST_DATABASE_HOST:-}" && -n "${TEST_DATABASE_NAME:-}" && -n "${TEST_DATABASE_USER:-}" && -n "${TEST_DATABASE_PASSWORD:-}" ]]; then
    return 0 2>/dev/null || exit 0
fi

if [[ -n "${GITHUB_ACTIONS:-}" ]]; then
    echo "ERROR: GitHub Actions job must define TEST_DATABASE_HOST, TEST_DATABASE_NAME, TEST_DATABASE_USER, and TEST_DATABASE_PASSWORD." >&2
    exit 1
fi

if [[ "${IS_DDEV_PROJECT:-false}" == "true" ]] || [[ -n "${DDEV_PROJECT:-}" ]]; then
    # Inside DDEV, the database service hostname and default credentials are defined by DDEV itself.
    export TEST_DATABASE_HOST="${TEST_DATABASE_HOST:-db}"
    export TEST_DATABASE_PORT="${TEST_DATABASE_PORT:-3306}"
    export TEST_DATABASE_USER="${TEST_DATABASE_USER:-db}"
    export TEST_DATABASE_PASSWORD="${TEST_DATABASE_PASSWORD:-db}"
    export TEST_DATABASE_NAME="${TEST_DATABASE_NAME:-db_test}"

    return 0 2>/dev/null || exit 0
fi

echo "ERROR: Unrecognized environment for integration tests." >&2
echo "  Set TEST_DATABASE_* explicitly, or run inside DDEV: ddev exec bash bin/run-tests.sh" >&2
exit 1
