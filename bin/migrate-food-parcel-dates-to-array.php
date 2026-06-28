<?php
/**
 * One-shot migration: FoodParcelDateLog rows → entryDates / exitDates array fields.
 *
 * Usage: ddev exec php bin/migrate-food-parcel-dates-to-array.php
 */

declare(strict_types=1);

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);
$pdo = $em->getPDO();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

echo "=== Migration: FoodParcel date logs → array fields ===\n\n";

$columnExists = static function (PDO $pdo, string $table, string $column): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => $table, ':c' => $column]);

    return (bool) $stmt->fetchColumn();
};

if (!$columnExists($pdo, 'food_parcel_registration', 'entry_dates')) {
    echo "Column entry_dates missing — run rebuild first.\n";
    exit(1);
}

$registrations = $pdo->query(
    'SELECT id FROM food_parcel_registration WHERE deleted = 0'
)->fetchAll(PDO::FETCH_COLUMN);

$migrated = 0;

foreach ($registrations as $registrationId) {
    $entity = $em->getEntityById('FoodParcelRegistration', $registrationId);

    if (!$entity) {
        continue;
    }

    $entryDates = $entity->get('entryDates');
    $exitDates = $entity->get('exitDates');

    if (is_array($entryDates) && $entryDates !== [] || is_array($exitDates) && $exitDates !== []) {
        continue;
    }

    $entryDates = is_array($entryDates) ? $entryDates : [];
    $exitDates = is_array($exitDates) ? $exitDates : [];

    if ($columnExists($pdo, 'food_parcel_date_log', 'log_date')) {
        $stmt = $pdo->prepare(
            'SELECT log_date FROM food_parcel_date_log
             WHERE deleted = 0 AND entry_registration_id = :id AND log_date IS NOT NULL
             ORDER BY log_date ASC'
        );
        $stmt->execute([':id' => $registrationId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
            $entryDates[] = $date;
        }

        $stmt = $pdo->prepare(
            'SELECT log_date FROM food_parcel_date_log
             WHERE deleted = 0 AND exit_registration_id = :id AND log_date IS NOT NULL
             ORDER BY log_date ASC'
        );
        $stmt->execute([':id' => $registrationId]);
        foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $date) {
            $exitDates[] = $date;
        }
    }

    if ($columnExists($pdo, 'food_parcel_date_log', 'entry_date')) {
        $stmt = $pdo->prepare(
            'SELECT entry_date, exit_date FROM food_parcel_date_log
             WHERE deleted = 0 AND food_parcel_registration_id = :id'
        );
        $stmt->execute([':id' => $registrationId]);
        foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
            if (!empty($row['entry_date'])) {
                $entryDates[] = $row['entry_date'];
            }
            if (!empty($row['exit_date'])) {
                $exitDates[] = $row['exit_date'];
            }
        }
    }

    $entryDates = array_values(array_unique(array_filter($entryDates)));
    $exitDates = array_values(array_unique(array_filter($exitDates)));
    sort($entryDates);
    sort($exitDates);

    if ($entryDates === [] && $exitDates === []) {
        continue;
    }

    $entity->set([
        'entryDates' => $entryDates,
        'exitDates' => $exitDates,
        'entryDatesText' => $entryDates === [] ? '' : implode("\n", $entryDates),
        'exitDatesText' => $exitDates === [] ? '' : implode("\n", $exitDates),
    ]);

    $em->saveEntity($entity, [SaveOption::SKIP_ALL => true]);
    $migrated++;
}

echo "Updated $migrated registration(s).\n";
