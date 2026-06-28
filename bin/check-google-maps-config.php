<?php
declare(strict_types=1);
include __DIR__ . '/../bootstrap.php';
$app = new Espo\Core\Application();
$app->setupSystemUser();
$c = $app->getContainer()->get('config');
$k = $c->get('googleMapsApiKey');
echo 'googleMapsApiKey: ' . (is_string($k) && $k !== '' ? 'SET (' . strlen($k) . ' chars)' : 'EMPTY') . PHP_EOL;
echo 'googleMapsMapId: ' . var_export($c->get('googleMapsMapId'), true) . PHP_EOL;
$em = $app->getContainer()->get('entityManager');
$int = $em->getEntityById('Integration', 'GoogleMaps');
if ($int) {
    echo 'Integration GoogleMaps enabled: ' . ($int->get('enabled') ? 'yes' : 'no') . PHP_EOL;
    echo 'Integration apiKey set: ' . ($int->get('apiKey') ? 'yes' : 'no') . PHP_EOL;
}
