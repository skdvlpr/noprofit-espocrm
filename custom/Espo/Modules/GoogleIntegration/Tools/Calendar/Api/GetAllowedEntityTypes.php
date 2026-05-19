<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\GoogleIntegration\Tools\Calendar\AllowedEntityTypesProvider;

class GetAllowedEntityTypes implements Action
{
    public function __construct(
        private AllowedEntityTypesProvider $allowedEntityTypesProvider
    ) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json([
            'list' => $this->allowedEntityTypesProvider->getReadableList(),
        ]);
    }
}
