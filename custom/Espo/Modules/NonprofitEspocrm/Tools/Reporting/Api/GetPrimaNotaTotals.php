<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Select\SearchParams;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\PrimaNotaStatsProvider;

class GetPrimaNotaTotals implements Action
{
    public function __construct(
        private PrimaNotaStatsProvider $primaNotaStatsProvider,
    ) {}

    public function process(Request $request): Response
    {
        $searchParams = SearchParams::fromRaw($request->getParsedBody() ?? []);

        $totals = $this->primaNotaStatsProvider->getTotals($searchParams);

        return ResponseComposer::json([
            'metricList' => array_keys($totals),
            ...$totals,
        ]);
    }
}
