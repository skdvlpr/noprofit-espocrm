<?php

require __DIR__.'/assert-test-database-env.php';

$config = require __DIR__.'/config-env.php';

$applicationDir = dirname(__DIR__, 2).'/application';

return array_merge($config, [
    'version' => '10.0.3',
    'lastModifiedTime' => is_dir($applicationDir) ? (int) filemtime($applicationDir) : 0,
    'defaultCurrency' => 'EUR',
    'baseCurrency' => 'EUR',
    'currencyList' => ['EUR'],
]);
