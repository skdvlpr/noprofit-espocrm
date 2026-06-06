<?php
/**
 * One-shot: copy Opportunity Google per-date data from legacy columns to unified names.
 *
 *   google_calendar_opportunity_date_list   -> google_calendar_date_source_list
 *   google_calendar_opportunity_event_settings -> google_calendar_event_settings
 *
 * Idempotent. Safe to re-run.
 *
 * Usage:
 *   ddev exec php bin/migrate-opportunity-google-calendar-fields.php
 *   ddev exec php clear_cache.php && ddev exec php rebuild.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;
use Espo\Modules\GoogleIntegration\Tools\Installer as GoogleIntegrationInstaller;

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);
$result = (new GoogleIntegrationInstaller())->migrateOpportunityGoogleCalendarFields($em);

echo "Opportunity Google field migration:\n";
echo "  date list rows updated: " . $result['dateList'] . "\n";
echo "  event settings rows updated: " . $result['eventSettings'] . "\n";
