<?php

declare(strict_types=1);


require __DIR__ . '/lib/refuse-production.php';


/**
 * Export CalendarDateSource + CalendarTemplate rows for migration backup.
 *
 * SECURITY: may contain org-specific labels; gitignored under deploy/backups/.
 *
 * Usage:
 *   ddev exec php bin/export-google-calendar-config.php [output.json]
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$out = $argv[1] ?? (dirname(__DIR__) . '/deploy/backups/google-calendar-config.json');

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

$exportEntity = static function (string $entityType) use ($em): array {
    $rows = [];
    foreach ($em->getRDBRepository($entityType)->find() as $entity) {
        $rows[] = $entity->getValueMap();
    }

    return $rows;
};

$data = [
    'version' => 1,
    'exportedAt' => gmdate('c'),
    'calendarDateSources' => $exportEntity('CalendarDateSource'),
    'calendarTemplates' => $exportEntity('CalendarTemplate'),
];

$dir = dirname($out);
if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
    fwrite(STDERR, "Cannot create directory: $dir\n");
    exit(1);
}

file_put_contents($out, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n");

echo 'Exported CalendarDateSource: ' . count($data['calendarDateSources']) . "\n";
echo 'Exported CalendarTemplate: ' . count($data['calendarTemplates']) . "\n";
echo "Written to: $out\n";
