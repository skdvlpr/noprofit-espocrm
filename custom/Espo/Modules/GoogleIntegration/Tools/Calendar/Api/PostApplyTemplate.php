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
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarTemplateApplier;
use Espo\ORM\EntityManager;

class PostApplyTemplate implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private CalendarTemplateApplier $calendarTemplateApplier
    ) {}

    public function process(Request $request): Response
    {
        $entityType = $request->getRouteParam('entityType') ?? throw new BadRequest();
        $id = $request->getRouteParam('id') ?? throw new BadRequest();
        $data = $request->getParsedBody();
        $templateId = $data->templateId ?? null;
        $sourceDateType = is_string($data->sourceDateType ?? null) ? $data->sourceDateType : 'main';

        if (!is_string($templateId) || $templateId === '') {
            throw new BadRequest('templateId is required.');
        }

        $record = $this->entityManager->getEntityById($entityType, $id);

        if ($record === null) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($record)) {
            throw new Forbidden();
        }

        $template = $this->entityManager->getEntityById('CalendarTemplate', $templateId);

        if ($template === null) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($template)) {
            throw new Forbidden();
        }

        return ResponseComposer::json($this->calendarTemplateApplier->apply($templateId, $record, $sourceDateType));
    }
}
