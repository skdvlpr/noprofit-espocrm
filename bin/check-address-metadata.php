<?php
declare(strict_types=1);
include __DIR__ . '/../bootstrap.php';
$app = new Espo\Core\Application();
$app->setupSystemUser();
$m = $app->getContainer()->get('metadata');
$m->init(true);
echo 'fields.address.view: ' . ($m->get(['fields', 'address', 'view']) ?? 'null') . PHP_EOL;
echo 'Contact.address.viewMap: ' . var_export($m->get(['entityDefs', 'Contact', 'fields', 'address', 'viewMap']), true) . PHP_EOL;
echo 'Member.address.type: ' . ($m->get(['entityDefs', 'Member', 'fields', 'address', 'type']) ?? 'null') . PHP_EOL;
echo 'Member.residenceAddress: ' . var_export($m->get(['entityDefs', 'Member', 'fields', 'residenceAddress']), true) . PHP_EOL;
