<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\ORM\EntityManager;

class GetDateOptions implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private DateSourceProvider $dateSourceProvider
    ) {}

    public function process(Request $request): Response
    {
        $entityType = $request->getRouteParam('entityType') ?? throw new BadRequest();
        $id = $request->getRouteParam('id') ?? throw new BadRequest();

        $record = $this->entityManager->getEntityById($entityType, $id);

        if ($record === null) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($record)) {
            throw new Forbidden();
        }

        return ResponseComposer::json([
            'dateSources' => $this->dateSourceProvider->getAvailableSourcesForRecord($record),
            'templates' => $this->dateSourceProvider->getReadableTemplates($entityType),
        ]);
    }
}
