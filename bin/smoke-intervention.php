<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke test for Intervention entity (post-launch epic A).
 *
 * Usage: ddev exec php bin/smoke-intervention.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Metadata;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
$em = $container->get('entityManager');
/** @var Metadata $metadata */
$metadata = $container->get('metadata');

$fail = 0;
$ok = static function (string $name, bool $pass, string $detail = '') use (&$fail): void {
    if (!$pass) {
        $fail++;
    }
    $mark = $pass ? 'PASS' : 'FAIL';
    echo "  [$mark] $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

echo "Intervention metadata\n";
$ok('scopes.entity', ($metadata->get(['scopes', 'Intervention', 'entity']) ?? false) === true);
$ok('entityDefs.department enum', is_array($metadata->get(['entityDefs', 'Intervention', 'fields', 'department', 'options'])));
$ok('linkParent parent field', $metadata->get(['entityDefs', 'Intervention', 'fields', 'parent', 'type']) === 'linkParent');
$ok('address locationAddress', $metadata->get(['entityDefs', 'Intervention', 'fields', 'locationAddress', 'type']) === 'address');
$ok('Task parent includes Intervention', in_array(
    'Intervention',
    $metadata->get(['entityDefs', 'Task', 'fields', 'parent', 'entityList']) ?? [],
    true
));
$ok('sidePanels tasks reference', ($metadata->get(['clientDefs', 'Intervention', 'sidePanels', 'detail', 0, 'reference']) ?? '') === 'tasks');
$ok('department style map', is_array($metadata->get(['entityDefs', 'Intervention', 'fields', 'department', 'style'])));
$listSmallPath = dirname(__DIR__) . '/custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/Task/listSmall.json';
$listSmall = json_decode((string) file_get_contents($listSmallPath), true);
$ok('Task listSmall includes category', is_array($listSmall) && in_array('category', array_column($listSmall, 'name'), true));

echo "\nIntervention CRUD\n";
$contact = $em->getNewEntity('Contact');
$contact->set([
    'firstName' => 'Smoke',
    'lastName' => 'Intervention' . date('His'),
]);
$em->saveEntity($contact);

$entity = $em->getNewEntity('Intervention');
$entity->set([
    'description' => 'SMOKE-Intervention-' . date('YmdHis'),
    'department' => 'StreetUnit',
    'interventionDate' => date('Y-m-d'),
    'interventionCount' => 1,
    'parentType' => 'Contact',
    'parentId' => $contact->getId(),
]);
$em->saveEntity($entity);
$ok('create', $entity->getId() !== null);
$ok('formula name', is_string($entity->get('name')) && $entity->get('name') !== '');
$ok('formula name translated department', str_contains((string) $entity->get('name'), 'Street Unit') || str_contains((string) $entity->get('name'), 'Unità'));

if ($entity->getId()) {
    $em->removeEntity($entity);
}
$em->removeEntity($contact);

echo $fail === 0 ? "\nALL PASS\n" : "\nFAILED: $fail\n";
exit($fail === 0 ? 0 : 1);
