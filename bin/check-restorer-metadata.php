<?php

require_once __DIR__ . '/../vendor/autoload.php';

$app = new \Espo\Core\Application();
$container = $app->getContainer();
$metadata = $container->getByClass(\Espo\Core\Utils\Metadata::class);

$entities = [
    'Account', 'Call', 'Campaign', 'Meeting', 'Member',
    'MealCount', 'Opportunity', 'Task', 'VolunteerEmployee',
    'GCalSmokeAllDay', 'GCalSmokeDateTime', 'GCalSmokeTwinDate',
];

foreach ($entities as $e) {
    $cls = $metadata->get(['recordDefs', $e, 'deletedRestorerClassName']);
    echo "$e: " . ($cls ?? 'null') . PHP_EOL;
}
