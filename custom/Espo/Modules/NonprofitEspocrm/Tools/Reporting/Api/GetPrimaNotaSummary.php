<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\NonprofitEspocrm\Tools\Reporting\PrimaNotaStatsProvider;

class GetPrimaNotaSummary implements Action
{
    public function __construct(
        private PrimaNotaStatsProvider $primaNotaStatsProvider,
    ) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json(
            $this->primaNotaStatsProvider->getSummary()
        );
    }
}
