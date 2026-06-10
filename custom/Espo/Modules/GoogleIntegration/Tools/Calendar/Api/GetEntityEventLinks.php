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
use Espo\Entities\User;
use Espo\ORM\EntityManager;

/**
 * Returns Google Calendar html links for the current user and a CRM record.
 */
class GetEntityEventLinks implements Action
{
    private const LINK_ENTITY_TYPE = 'GoogleCalendarEventLink';

    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private User $user
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

        $collection = $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entityType,
                'sourceEntityId' => $id,
                'userId' => $this->user->getId(),
            ])
            ->find();

        $list = [];

        foreach ($collection as $link) {
            $htmlLink = $link->get('googleEventHtmlLink');

            if (!is_string($htmlLink) || $htmlLink === '') {
                continue;
            }

            $sourceDateType = $link->get('sourceDateType');

            if (!is_string($sourceDateType) || $sourceDateType === '') {
                continue;
            }

            $list[] = [
                'sourceDateType' => $sourceDateType,
                'htmlLink' => $htmlLink,
            ];
        }

        return ResponseComposer::json([
            'list' => $list,
        ]);
    }
}
