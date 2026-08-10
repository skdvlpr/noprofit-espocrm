<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;

class GetIntegrationStatus implements Action
{
    public function __construct(
        private IntegrationState $integrationState,
        private ManagerCalendarShare $managerCalendarShare,
        private User $user,
    ) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json([
            'enabled' => $this->integrationState->isGoogleCalendarDriveEnabled(),
            'canShareToOthers' => $this->managerCalendarShare->actorCanShare($this->user),
        ]);
    }
}
