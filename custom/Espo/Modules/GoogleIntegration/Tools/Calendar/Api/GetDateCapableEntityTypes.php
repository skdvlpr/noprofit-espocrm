<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateCapableEntityTypesProvider;

class GetDateCapableEntityTypes implements Action
{
    public function __construct(
        private DateCapableEntityTypesProvider $dateCapableEntityTypesProvider
    ) {}

    public function process(Request $request): Response
    {
        return ResponseComposer::json([
            'list' => $this->dateCapableEntityTypesProvider->getReadableList(),
        ]);
    }
}
