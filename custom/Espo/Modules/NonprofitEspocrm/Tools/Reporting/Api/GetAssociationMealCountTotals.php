<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Select\SearchParams;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\AssociationMealCountStatsProvider;

class GetAssociationMealCountTotals implements Action
{
    public function __construct(
        private AssociationMealCountStatsProvider $statsProvider,
    ) {}

    public function process(Request $request): Response
    {
        $searchParams = SearchParams::fromRaw($request->getParsedBody() ?? []);
        $totals = $this->statsProvider->getTotals($searchParams);

        return ResponseComposer::json([
            'metricList' => array_keys($totals),
            ...$totals,
        ]);
    }
}
