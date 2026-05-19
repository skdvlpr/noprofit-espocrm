<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;

class GetTemplateOptions implements Action
{
    public function __construct(
        private DateSourceProvider $dateSourceProvider,
        private Acl $acl
    ) {}

    public function process(Request $request): Response
    {
        $entityType = $request->getRouteParam('entityType') ?? throw new BadRequest();

        if (!$this->acl->checkScope($entityType)) {
            throw new Forbidden();
        }

        return ResponseComposer::json([
            'templates' => $this->dateSourceProvider->getReadableTemplates($entityType),
        ]);
    }
}
