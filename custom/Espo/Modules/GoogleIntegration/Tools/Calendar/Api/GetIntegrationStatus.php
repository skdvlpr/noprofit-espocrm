<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;

class GetIntegrationStatus implements Action
{
    public function __construct(
        private IntegrationState $integrationState,
    ) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json([
            'enabled' => $this->integrationState->isGoogleCalendarDriveEnabled(),
        ]);
    }
}
