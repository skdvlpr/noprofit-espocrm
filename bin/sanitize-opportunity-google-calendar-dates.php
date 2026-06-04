<?php
/**
 * One-shot cleanup: remove invalid values from googleCalendarDateSourceList (e.g. "crea").
 *
 * Usage:
 *   ddev exec php bin/sanitize-opportunity-google-calendar-dates.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

const ALLOWED = ['presentationDate', 'closeDate'];

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$updated = 0;

foreach ($em->getRDBRepository('Opportunity')->find() as $opportunity) {
    $selected = $opportunity->get('googleCalendarDateSourceList');

    if (!is_array($selected)) {
        continue;
    }

    $filtered = array_values(array_unique(array_filter(
        $selected,
        static fn ($item) => is_string($item) && in_array($item, ALLOWED, true)
    )));

    if ($filtered === $selected) {
        continue;
    }

    if ($filtered === []) {
        $filtered = ['closeDate'];
    }

    $opportunity->set('googleCalendarDateSourceList', $filtered);
    $em->saveEntity($opportunity);
    $updated++;
}

echo "Sanitized Opportunity googleCalendarDateSourceList on {$updated} record(s).\n";
