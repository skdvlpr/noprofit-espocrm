<?php

require __DIR__ . '/lib/refuse-production.php';


/**
 * Smoke: Case intake requires linkParent; metadata has NGO type options.
 *
 * Usage:
 *   ddev exec php bin/smoke-case-intake.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

$failures = 0;
$report = static function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }
    $marker = $pass ? '[PASS]' : '[FAIL]';
    echo "  $marker $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$metadata = $container->getByClass(Metadata::class);
$metadata->init(true);

$parentField = $metadata->get(['entityDefs', 'Case', 'fields', 'parent']);
$typeField = $metadata->get(['entityDefs', 'Case', 'fields', 'type']);

$report(
    'Case.parent is required linkParent',
    ($parentField['type'] ?? null) === 'linkParent'
        && ($parentField['required'] ?? false) === true
);
$report(
    'Case.parent entityList includes Member and VolunteerEmployee',
    in_array('Member', $parentField['entityList'] ?? [], true)
        && in_array('VolunteerEmployee', $parentField['entityList'] ?? [], true)
);
$report(
    'Case.type has NGO intake options',
    in_array('BeneficiaryRequest', $typeField['options'] ?? [], true)
        && ($typeField['required'] ?? false) === true
);

$entityManager = $container->getByClass(EntityManager::class);

/** @var ?\Espo\ORM\Entity $contact */
$contact = $entityManager->getRDBRepository('Contact')
    ->select(['id'])
    ->limit(0, 1)
    ->findOne();

if ($contact === null) {
    $report('Case create without parent rejected (400)', false, 'no Contact seed');
} else {
    $case = $entityManager->getRDBRepository('Case')->getNew();
    $case->set([
        'name' => 'Smoke intake missing parent ' . bin2hex(random_bytes(4)),
        'type' => 'InformationRequest',
    ]);

    try {
        $entityManager->saveEntity($case);
        $report('Case create without parent rejected (400)', false, 'save succeeded unexpectedly');
        if ($case->hasId()) {
            $entityManager->removeEntity($case);
        }
    } catch (\Throwable $e) {
        $report('Case create without parent rejected (400)', true, $e->getMessage());
    }

    $caseOk = $entityManager->getRDBRepository('Case')->getNew();
    $caseOk->set([
        'name' => 'Smoke intake with parent ' . bin2hex(random_bytes(4)),
        'type' => 'GuestIntake',
        'parentType' => 'Contact',
        'parentId' => $contact->getId(),
    ]);

    try {
        $entityManager->saveEntity($caseOk);
        $report('Case create with parent succeeds', $caseOk->hasId());
        if ($caseOk->hasId()) {
            $entityManager->removeEntity($caseOk);
        }
    } catch (\Throwable $e) {
        $report('Case create with parent succeeds', false, $e->getMessage());
    }
}

echo $failures === 0 ? "\nALL PASS\n" : "\nFAILURES: $failures\n";
exit($failures === 0 ? 0 : 1);
