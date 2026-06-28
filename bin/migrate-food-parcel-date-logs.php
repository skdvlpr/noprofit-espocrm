<?php
/**
 * One-shot migration: paired entry/exit columns → separate entry/exit date lists.
 *
 * Usage: ddev exec php bin/migrate-food-parcel-date-logs.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\DateLogsTextSync;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();
/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$columnExists = static function (PDO $pdo, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => 'food_parcel_date_log', ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

if (!$columnExists($pdo, 'food_parcel_registration_id')) {
    echo "Legacy columns already absent — skipping data migration.\n";
    exit(0);
}

echo "=== Migration: FoodParcel date lists (entrata / uscita) ===\n\n";

$rows = $pdo->query(
    'SELECT id, food_parcel_registration_id, entry_date, exit_date
     FROM food_parcel_date_log
     WHERE deleted = 0'
)->fetchAll(PDO::FETCH_ASSOC);

$migrated = 0;
$registrationIds = [];

foreach ($rows as $row) {
    $parentId = $row['food_parcel_registration_id'] ?? null;

    if (!$parentId) {
        continue;
    }

    $registrationIds[$parentId] = true;
    $entryDate = $row['entry_date'] ?? null;
    $exitDate = $row['exit_date'] ?? null;
    $rowId = $row['id'];

    if ($entryDate && $exitDate) {
        $entry = $em->getNewEntity('FoodParcelDateLog');
        $entry->set([
            'logDate' => $entryDate,
            'entryRegistrationId' => $parentId,
            'name' => $entryDate,
        ]);
        $em->saveEntity($entry, [SaveOption::SKIP_ALL => true]);

        $exit = $em->getNewEntity('FoodParcelDateLog');
        $exit->set([
            'logDate' => $exitDate,
            'exitRegistrationId' => $parentId,
            'name' => $exitDate,
        ]);
        $em->saveEntity($exit, [SaveOption::SKIP_ALL => true]);

        $pdo->prepare('UPDATE food_parcel_date_log SET deleted = 1 WHERE id = ?')->execute([$rowId]);
        $migrated += 2;
        continue;
    }

    $entity = $em->getEntityById('FoodParcelDateLog', $rowId);

    if (!$entity) {
        continue;
    }

    if ($entryDate) {
        $entity->set([
            'logDate' => $entryDate,
            'entryRegistrationId' => $parentId,
            'exitRegistrationId' => null,
            'name' => $entryDate,
        ]);
    } elseif ($exitDate) {
        $entity->set([
            'logDate' => $exitDate,
            'exitRegistrationId' => $parentId,
            'entryRegistrationId' => null,
            'name' => $exitDate,
        ]);
    } else {
        $pdo->prepare('UPDATE food_parcel_date_log SET deleted = 1 WHERE id = ?')->execute([$rowId]);
        continue;
    }

    $em->saveEntity($entity, [SaveOption::SKIP_ALL => true]);
    $migrated++;
}

if ($registrationIds !== []) {
    /** @var InjectableFactory $injectableFactory */
    $injectableFactory = $container->getByClass(InjectableFactory::class);
    $sync = $injectableFactory->create(DateLogsTextSync::class);

    foreach (array_keys($registrationIds) as $registrationId) {
        $sync->syncForRegistrationId($registrationId);
    }
}

echo "Migrated $migrated date log row(s) across " . count($registrationIds) . " registration(s).\n";
