<?php

declare(strict_types=1);

/**
 * Fail closed before integration tests touch MySQL.
 *
 * Database credentials must come from the environment (GitHub Actions workflow or
 * bin/lib/test-database-env.sh inside DDEV). No host/user/password fallbacks.
 */

$required = [
    'TEST_DATABASE_HOST',
    'TEST_DATABASE_NAME',
    'TEST_DATABASE_USER',
    'TEST_DATABASE_PASSWORD',
];

$missing = [];

foreach ($required as $key) {
    $value = getenv($key);

    if ($value === false || $value === '') {
        $missing[] = $key;
    }
}

if ($missing !== []) {
    throw new RuntimeException(
        'Integration tests require isolated TEST_DATABASE_* env vars. Missing: '
        .implode(', ', $missing)
        .'. On GitHub Actions set them in the workflow; locally run bash bin/run-tests.sh inside DDEV.'
    );
}

$dbname = (string) getenv('TEST_DATABASE_NAME');

if ($dbname === 'db') {
    throw new RuntimeException(
        'REFUSED: TEST_DATABASE_NAME must be an isolated test database (e.g. db_test), not the DDEV dev database "db".'
    );
}

$root = dirname(__DIR__, 2);
$configFile = $root.'/data/config.php';

if (is_file($configFile)) {
    /** @var mixed $mainConfig */
    $mainConfig = include $configFile;

    if (is_array($mainConfig)) {
        $devDbname = (string) ($mainConfig['database']['dbname'] ?? '');

        if ($devDbname !== '' && $dbname === $devDbname) {
            throw new RuntimeException(
                "REFUSED: TEST_DATABASE_NAME ({$dbname}) matches data/config.php dev/prod database."
            );
        }
    }
}
