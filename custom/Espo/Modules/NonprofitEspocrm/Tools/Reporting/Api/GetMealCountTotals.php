<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Select\SearchParams;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\MealCountStatsProvider;

/**
 * Filter-aware totals for MealCount list footer (Task 7.3.3).
 * Accepts the same search payload as list views (where, primaryFilter, textFilter).
 */
class GetMealCountTotals implements Action
{
    public function __construct(
        private MealCountStatsProvider $mealCountStatsProvider,
    ) {}

    public function process(Request $request): Response
    {
        $searchParams = SearchParams::fromRaw($request->getParsedBody() ?? []);

        $totals = $this->mealCountStatsProvider->getTotals($searchParams);

        return ResponseComposer::json([
            'metricList' => array_keys($totals),
            ...$totals,
        ]);
    }
}
