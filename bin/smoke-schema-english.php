<?php
/**
 * Smoke test for English-schema invariants (Safehouse CRM).
 *
 * Verifies:
 *   - `account.sector` enum accepts only English keys (`ThirdSector`,
 *     `SocialWorkers`, `Public`);
 *   - `opportunity.stage` enum accepts only English keys (`Preparation`,
 *     `Proposal`, `Negotiation`, `Closed Won`, `Closed Lost`);
 *   - `opportunity.presentationDate` (DB column `presentation_date`) is
 *     readable/writable via the ORM;
 *   - the legacy column names (`settore`, `data_presentazione`) no longer
 *     exist in the schema.
 *
 * Each create is deleted on success. Run is idempotent.
 *
 * Usage:
 *   ddev exec php bin/smoke-schema-english.php
 */

include __DIR__ . '/../bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$app = new Application();
$app->setupSystemUser();
$container = $app->getContainer();

/** @var EntityManager $em */
$em = $container->getByClass(EntityManager::class);

/** @var PDO $pdo */
$pdo = $em->getPDO();

$failures = 0;
$report = function (string $name, bool $pass, string $detail = '') use (&$failures): void {
    if (!$pass) {
        $failures++;
    }
    $marker = $pass ? '[PASS]' : '[FAIL]';
    echo "  $marker $name" . ($detail !== '' ? " — $detail" : '') . "\n";
};

$columnExists = function (string $table, string $column) use ($pdo): bool {
    $stmt = $pdo->prepare(
        'SELECT 1 FROM information_schema.COLUMNS '
        . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
    );
    $stmt->execute([':t' => $table, ':c' => $column]);
    return (bool) $stmt->fetchColumn();
};

echo "Schema layout assertions\n";
$report('account.sector column exists', $columnExists('account', 'sector'));
$report('account.settore column does NOT exist', !$columnExists('account', 'settore'));
$report('opportunity.presentation_date column exists', $columnExists('opportunity', 'presentation_date'));
$report('opportunity.data_presentazione column does NOT exist', !$columnExists('opportunity', 'data_presentazione'));

echo "\nAccount sector enum (English keys)\n";

$marker = bin2hex(random_bytes(4));

$createdIds = [];

foreach (['ThirdSector', 'SocialWorkers', 'Public'] as $sectorKey) {
    $entity = $em->createEntity('Account', [
        'name'   => "Smoke Sector $sectorKey $marker",
        'sector' => $sectorKey,
    ]);
    $createdIds['Account'][] = $entity->getId();

    $fresh = $em->getRDBRepository('Account')->getById($entity->getId());
    $report("Account.sector accepts '$sectorKey'", $fresh && $fresh->get('sector') === $sectorKey,
        "got=" . ($fresh ? var_export($fresh->get('sector'), true) : 'null'));
}

echo "\nOpportunity stage + presentationDate (English keys)\n";

$stageOptions = ['Preparation', 'Proposal', 'Negotiation', 'Closed Won', 'Closed Lost'];
$presentationDate = (new DateTimeImmutable('today'))->format('Y-m-d');

foreach ($stageOptions as $stageKey) {
    $entity = $em->createEntity('Opportunity', [
        'name'             => "Smoke Stage $stageKey $marker",
        'stage'            => $stageKey,
        'presentationDate' => $presentationDate,
        'closeDate'        => $presentationDate,
        'amount'           => 100.0,
        'amountCurrency'   => 'EUR',
    ]);
    $createdIds['Opportunity'][] = $entity->getId();

    $fresh = $em->getRDBRepository('Opportunity')->getById($entity->getId());
    $report("Opportunity.stage accepts '$stageKey'", $fresh && $fresh->get('stage') === $stageKey,
        "got=" . ($fresh ? var_export($fresh->get('stage'), true) : 'null'));
    $report("Opportunity.presentationDate round-trips for '$stageKey'",
        $fresh && $fresh->get('presentationDate') === $presentationDate,
        "got=" . ($fresh ? var_export($fresh->get('presentationDate'), true) : 'null'));
}

echo "\nCleanup\n";
foreach ($createdIds as $type => $ids) {
    foreach ($ids as $id) {
        $entity = $em->getRDBRepository($type)->getById($id);
        if ($entity) {
            $em->removeEntity($entity);
        }
    }
}
echo "  removed " . count($createdIds['Account'] ?? []) . " Account + " . count($createdIds['Opportunity'] ?? []) . " Opportunity test rows\n";

echo "\n=== ";
echo $failures === 0 ? 'ALL PASS' : ($failures . ' FAILURE(S)');
echo " ===\n";
exit($failures === 0 ? 0 : 1);
