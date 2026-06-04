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

$app = new Application();
$app->setupSystemUser();

/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$emptyList = "google_calendar_date_source_list IS NULL OR google_calendar_date_source_list = '' OR google_calendar_date_source_list = '[]'";
$hasLegacyList = "google_calendar_opportunity_date_list IS NOT NULL AND google_calendar_opportunity_date_list != '' AND google_calendar_opportunity_date_list != '[]'";

$sqlList = "UPDATE opportunity SET google_calendar_date_source_list = google_calendar_opportunity_date_list WHERE ({$emptyList}) AND ({$hasLegacyList})";
$listUpdated = $pdo->exec($sqlList);

$emptySettings = "google_calendar_event_settings IS NULL OR google_calendar_event_settings = '' OR google_calendar_event_settings = '[]'";
$hasLegacySettings = "google_calendar_opportunity_event_settings IS NOT NULL AND google_calendar_opportunity_event_settings != '' AND google_calendar_opportunity_event_settings != '[]'";

$sqlSettings = "UPDATE opportunity SET google_calendar_event_settings = google_calendar_opportunity_event_settings WHERE ({$emptySettings}) AND ({$hasLegacySettings})";
$settingsUpdated = $pdo->exec($sqlSettings);

echo "Opportunity Google field migration:\n";
echo "  date list rows updated: " . (int) $listUpdated . "\n";
echo "  event settings rows updated: " . (int) $settingsUpdated . "\n";
