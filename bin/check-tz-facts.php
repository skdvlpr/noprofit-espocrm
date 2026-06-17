<?php

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\ORM\EntityManager;

$app = new Application();
$container = $app->getContainer();
$config = $container->getByClass(Config::class);
$em = $container->getByClass(EntityManager::class);

echo "app timeZone: " . ($config->get('timeZone') ?? 'null') . "\n";

$admin = $em->getRDBRepository('User')->where(['userName' => 'admin'])->findOne();
$prefs = $admin?->get('preferences');
echo "admin pref timeZone: " . ($prefs?->get('timeZone') ?? 'null') . "\n";

foreach ([
    ['Call', '6a2b2f69b915e0355'],
    ['Meeting', '6a2b2f6e9252142ef'],
    ['GCalSmokeDateTime', '6a2b2f6c9166345a2'],
] as [$type, $id]) {
    $e = $em->getEntityById($type, $id);
    if ($e === null) {
        continue;
    }
    echo "{$type} dateStart: " . $e->get('dateStart') . " | dateEnd: " . $e->get('dateEnd') . "\n";
}
