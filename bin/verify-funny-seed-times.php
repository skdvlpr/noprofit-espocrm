<?php

declare(strict_types=1);

require __DIR__ . '/lib/GcalTestFixtures.php';

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$app = new Application();
$em = $app->getContainer()->getByClass(EntityManager::class);

/** @var array<string, array{fields: list<string>}> $expect */
$expect = [
    'Account' => ['fields' => ['name', 'cDataFirmaContratto']],
    'Call' => ['fields' => ['name', 'dateStart', 'dateEnd']],
    'Meeting' => ['fields' => ['name', 'dateStart', 'dateEnd']],
    'Task' => ['fields' => ['name', 'dateStart', 'dateEnd']],
    'Opportunity' => ['fields' => ['name', 'presentationDate', 'closeDate']],
    'VolunteerEmployee' => ['fields' => ['firstName', 'lastName', 'startDate', 'endDate']],
    'Member' => ['fields' => ['firstName', 'lastName', 'birthDate']],
    'Campaign' => ['fields' => ['name', 'startDate']],
    'GCalSmokeAllDay' => ['fields' => ['name', 'eventDate']],
    'GCalSmokeDateTime' => ['fields' => ['name', 'dateStart', 'dateEnd']],
    'GCalSmokeTwinDate' => ['fields' => ['name', 'primaryDate', 'reviewDate']],
];

echo "=== CRM funny T- records (current) ===\n\n";

foreach ($expect as $entityType => $cfg) {
    $nf = GcalTestFixtures::nameField($entityType);
    $record = $em->getRDBRepository($entityType)
        ->where(["{$nf}*" => 'T-%', 'deleted' => false])
        ->order('createdAt', 'DESC')
        ->findOne();

    if ($record === null) {
        echo "[MISSING] {$entityType}\n\n";
        continue;
    }

    $parts = [];
    foreach ($cfg['fields'] as $field) {
        $parts[] = "{$field}=" . ($record->get($field) ?? 'null');
    }

    $links = $em->getRDBRepository('GoogleCalendarEventLink')
        ->where([
            'sourceEntityType' => $entityType,
            'sourceEntityId' => $record->getId(),
            'deleted' => false,
        ])
        ->count();

    echo "[{$entityType}] id={$record->getId()} links={$links}\n  " . implode(' | ', $parts) . "\n\n";
}
