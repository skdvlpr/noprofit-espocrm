<?php
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\ORM\EntityManager;

$ids = array_slice($argv, 1);
if ($ids === []) {
    fwrite(STDERR, "Usage: php bin/print-prima-nota-statuses.php <id>...\n");
    exit(1);
}

$app = new Application();
$app->setupSystemUser();
/** @var EntityManager $em */
$em = $app->getContainer()->getByClass(EntityManager::class);

foreach ($ids as $id) {
    $e = $em->getEntityById('PrimaNota', $id);
    if ($e === null) {
        echo json_encode(['id' => $id, 'missing' => true]) . "\n";
        continue;
    }
    echo json_encode([
        'id' => $id,
        'paymentStatus' => $e->get('paymentStatus'),
        'amount' => $e->get('amount'),
        'stripePayoutId' => $e->get('stripePayoutId'),
        'stripeChargeId' => $e->get('stripeChargeId'),
    ], JSON_UNESCAPED_UNICODE) . "\n";
}
