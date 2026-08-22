<?php

/**
 * Integration test database config.
 *
 * DDEV defaults (host `db`) when env vars are unset; GitHub Actions CI sets
 * TEST_DATABASE_* in `.github/workflows/ci.yml` (127.0.0.1 + espo_test user).
 *
 * @see https://docs.espocrm.com/development/tests/
 */
return [
    'database' => [
        'driver' => 'pdo_mysql',
        'host' => getenv('TEST_DATABASE_HOST') ?: 'db',
        'port' => getenv('TEST_DATABASE_PORT') ?: '',
        'charset' => getenv('TEST_DATABASE_CHARSET') ?: 'utf8mb4',
        'dbname' => getenv('TEST_DATABASE_NAME') ?: 'db_test',
        'user' => getenv('TEST_DATABASE_USER') ?: 'db',
        'password' => getenv('TEST_DATABASE_PASSWORD') ?: 'db',
    ],
    'version' => '10.0.3',
    'lastModifiedTime' => 1777526190,
];
