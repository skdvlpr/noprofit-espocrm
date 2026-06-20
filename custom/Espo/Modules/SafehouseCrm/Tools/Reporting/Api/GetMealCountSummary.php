<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\SafehouseCrm\Tools\Reporting\MealCountStatsProvider;

class GetMealCountSummary implements Action
{
    public function __construct(
        private MealCountStatsProvider $mealCountStatsProvider,
    ) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json(
            $this->mealCountStatsProvider->getSummary()
        );
    }
}
