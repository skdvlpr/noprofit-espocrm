<?php

/**
 * Sync CalendarDateSource.title suffix labels from CalendarDateSourceDefaults catalog.
 * Creates missing default rows; updates label on existing rows for known keys.
 *
 * Usage:
 *   ddev exec php bin/sync-calendar-date-source-labels.php
 *   ddev exec php bin/sync-calendar-date-source-labels.php --dry-run
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\DataManager;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarDateSourceDefaults;
use Espo\ORM\EntityManager;

$dryRun = in_array('--dry-run', $argv ?? [], true);

$app = new Application();
$app->setupSystemUser();
$em = $app->getContainer()->getByClass(EntityManager::class);
$repo = $em->getRDBRepository('CalendarDateSource');

/** @var array<string, array<string, mixed>> $catalog */
$catalog = [];

foreach (CalendarDateSourceDefaults::sources() as $source) {
    $key = CalendarDateSourceDefaults::labelKey(
        (string) $source['targetEntityType'],
        (string) ($source['sourceDateType'] ?? 'main')
    );
    $catalog[$key] = $source;
}

// GCal smoke entities (optional extension test targets).
$smokeSources = [
    [
        'name' => 'GCalSmokeAllDay — event date',
        'targetEntityType' => 'GCalSmokeAllDay',
        'dateField' => 'eventDate',
        'endDateField' => null,
        'sourceDateType' => 'main',
        'label' => CalendarDateSourceDefaults::CANONICAL_LABELS['GCalSmokeAllDay:main'],
        'allDay' => true,
        'sortOrder' => 9001,
    ],
    [
        'name' => 'GCalSmokeDateTime — interval',
        'targetEntityType' => 'GCalSmokeDateTime',
        'dateField' => 'dateStart',
        'endDateField' => 'dateEnd',
        'sourceDateType' => 'main',
        'label' => CalendarDateSourceDefaults::CANONICAL_LABELS['GCalSmokeDateTime:main'],
        'allDay' => false,
        'sortOrder' => 9002,
    ],
    [
        'name' => 'GCalSmokeTwinDate — primary',
        'targetEntityType' => 'GCalSmokeTwinDate',
        'dateField' => 'primaryDate',
        'endDateField' => null,
        'sourceDateType' => 'primaryDate',
        'label' => CalendarDateSourceDefaults::CANONICAL_LABELS['GCalSmokeTwinDate:primaryDate'],
        'allDay' => true,
        'sortOrder' => 9003,
    ],
    [
        'name' => 'GCalSmokeTwinDate — review',
        'targetEntityType' => 'GCalSmokeTwinDate',
        'dateField' => 'reviewDate',
        'endDateField' => null,
        'sourceDateType' => 'reviewDate',
        'label' => CalendarDateSourceDefaults::CANONICAL_LABELS['GCalSmokeTwinDate:reviewDate'],
        'allDay' => true,
        'sortOrder' => 9004,
    ],
];

foreach ($smokeSources as $source) {
    $key = CalendarDateSourceDefaults::labelKey(
        (string) $source['targetEntityType'],
        (string) $source['sourceDateType']
    );
    $catalog[$key] = $source;
}

echo '=== Sync CalendarDateSource labels ===' . ($dryRun ? ' (DRY RUN)' : '') . "\n\n";

$created = 0;
$updated = 0;

foreach ($catalog as $key => $source) {
    $entityType = (string) $source['targetEntityType'];
    $sourceDateType = (string) ($source['sourceDateType'] ?? 'main');
    $label = (string) ($source['label'] ?? '');

    $existing = $repo
        ->where([
            'targetEntityType' => $entityType,
            'sourceDateType' => $sourceDateType,
            'deleted' => false,
        ])
        ->findOne();

    if ($existing === null) {
        echo "  [create] {$key} → label=\"{$label}\"\n";

        if (!$dryRun) {
            $em->saveEntity($em->createEntity('CalendarDateSource', array_merge([
                'isActive' => true,
                'calendarViewEnabled' => true,
            ], $source)));
        }

        $created++;
        continue;
    }

    $current = trim((string) $existing->get('label'));

    if ($current === $label) {
        echo "  [ok] {$key} label=\"{$label}\"\n";
        continue;
    }

    echo "  [update] {$key}: \"{$current}\" → \"{$label}\"\n";

    if (!$dryRun) {
        $existing->set('label', $label);
        $em->saveEntity($existing);
    }

    $updated++;
}

// Warn on active rows without a non-empty label (title suffix would be name-only).
$missingLabel = 0;

foreach ($repo->where(['deleted' => false, 'isActive' => true])->find() as $row) {
    $label = trim((string) $row->get('label'));

    if ($label !== '') {
        continue;
    }

    $key = CalendarDateSourceDefaults::labelKey(
        (string) $row->get('targetEntityType'),
        (string) ($row->get('sourceDateType') ?: 'main')
    );
    echo "  [WARN] active source {$key} has empty label — Google title will omit suffix\n";
    $missingLabel++;
}

echo "\n=== DONE: created {$created}, updated {$updated}, empty-label warnings {$missingLabel} ===\n";

if (!$dryRun && ($created > 0 || $updated > 0)) {
    $app->getContainer()->getByClass(DataManager::class)->rebuild();
    passthru('php clear_cache.php');
}

exit($missingLabel > 0 ? 1 : 0);
