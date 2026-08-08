<?php

declare(strict_types=1);

/**
 * Local DDEV probe: time site bulk-pull and observe PrimaNota counts/statuses.
 * Self-delete on success via ephemeral helper when present.
 */

require __DIR__ . '/lib/refuse-production.php';

$root = dirname(__DIR__);
require $root . '/bootstrap.php';

$app = new \Espo\Core\Application();
$app->setupSystemUser();
$container = $app->getContainer();
$config = $container->get('config');
$em = $container->get('entityManager');

$siteUrl = rtrim(trim((string) $config->get('safehouseDonationSiteUrl')), '/');
$token = trim((string) $config->get('safehouseCrmSyncToken'));

if ($siteUrl === '' || $token === '') {
    fwrite(STDERR, "Missing safehouseDonationSiteUrl / safehouseCrmSyncToken\n");
    exit(1);
}

function countStripe(?\Espo\ORM\EntityManager $em): array
{
    $repo = $em->getRDBRepository('PrimaNota');
    $total = $repo->where(['donationPaymentProvider' => 'Stripe'])->count();
    $planned = $repo->where([
        'donationPaymentProvider' => 'Stripe',
        'paymentStatus' => 'Planned',
    ])->count();
    $inviato = $repo->where([
        'donationPaymentProvider' => 'Stripe',
        'paymentStatus' => 'Inviato',
    ])->count();

    return compact('total', 'planned', 'inviato');
}

$before = countStripe($em);
echo "BEFORE " . json_encode($before) . PHP_EOL;

$url = $siteUrl . '/api/internal/prima-nota/bulk-pull';
$payload = json_encode([
    'providers' => ['Stripe'],
    'currencies' => ['EUR'],
    'mode' => 'all',
    'maxItems' => 30,
], JSON_THROW_ON_ERROR);

$t0 = microtime(true);
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => [
        'Accept: application/json',
        'Content-Type: application/json',
        'X-Safehouse-Sync-Token: ' . $token,
    ],
    CURLOPT_POSTFIELDS => $payload,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 600,
    CURLOPT_CONNECTTIMEOUT => 10,
]);
$body = curl_exec($ch);
$errno = curl_errno($ch);
$err = curl_error($ch);
$code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
$elapsed = round(microtime(true) - $t0, 2);
curl_close($ch);

echo "HTTP {$code} elapsed={$elapsed}s errno={$errno} err={$err}\n";
if (is_string($body) && $body !== '') {
    $decoded = json_decode($body, true);
    if (is_array($decoded)) {
        echo "RESULT keys=" . implode(',', array_keys($decoded)) . PHP_EOL;
        foreach (['scanned','created','updated','duplicate','skipped','failed','markedInviato','statusRefreshed','truncated'] as $k) {
            if (array_key_exists($k, $decoded)) {
                echo "  {$k}=" . json_encode($decoded[$k]) . PHP_EOL;
            }
        }
        if (!empty($decoded['log']) && is_array($decoded['log'])) {
            echo "LOG (" . count($decoded['log']) . " steps):\n";
            foreach ($decoded['log'] as $step) {
                echo '  - ' . (is_string($step) ? $step : json_encode($step, JSON_UNESCAPED_UNICODE)) . PHP_EOL;
            }
        }
    } else {
        echo "BODY " . substr($body, 0, 500) . PHP_EOL;
    }
}

$afterImmediate = countStripe($em);
echo "AFTER_IMMEDIATE " . json_encode($afterImmediate) . PHP_EOL;

sleep(3);
$afterWait = countStripe($em);
echo "AFTER_WAIT_3S " . json_encode($afterWait) . PHP_EOL;

echo "DONE\n";
