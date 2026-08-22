<?php

/**
 * Environment-driven integration test config (Espo upstream default).
 *
 * @see https://docs.espocrm.com/development/tests/
 */

require __DIR__.'/assert-test-database-env.php';

return [
    'database' => [
        'platform' => getenv('TEST_DATABASE_PLATFORM') ?: 'Mysql',
        'charset' => getenv('TEST_DATABASE_CHARSET') ?: 'utf8mb4',
        'host' => getenv('TEST_DATABASE_HOST'),
        'port' => getenv('TEST_DATABASE_PORT'),
        'dbname' => getenv('TEST_DATABASE_NAME'),
        'user' => getenv('TEST_DATABASE_USER'),
        'password' => getenv('TEST_DATABASE_PASSWORD'),
    ],
];
