<?php
/** Print PrimaNota dashlet summary JSON (DDEV QA helper). */
declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use Espo\Core\Application;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\PrimaNotaStatsProvider;

$app = new Application();
$app->setupSystemUser();
$p = $app->getContainer()->get('injectableFactory')->create(PrimaNotaStatsProvider::class);
$s = $p->getSummary();

echo json_encode([
    'bankBalance' => $s->bankBalance->balance ?? null,
    'cashBalance' => $s->cashBalance->balance ?? null,
    'cashOpening' => $s->cashBalance->opening ?? null,
    'month' => [
        'amountIn' => $s->month->amountIn ?? null,
        'amountOut' => $s->month->amountOut ?? null,
        'managementBalance' => $s->month->managementBalance ?? null,
        'plannedAmountIn' => $s->month->plannedAmountIn ?? null,
        'plannedAmountOut' => $s->month->plannedAmountOut ?? null,
        'plannedBalance' => $s->month->plannedBalance ?? null,
    ],
    'today' => [
        'amountIn' => $s->today->amountIn ?? null,
        'plannedAmountIn' => $s->today->plannedAmountIn ?? null,
    ],
], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . "\n";
