<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\OverlaySyncRunner;
use Espo\ORM\EntityManager;

/**
 * Returns overlay Google events for the current user for CRM calendar merge.
 */
class GetOverlayEvents implements Action
{
    public function __construct(
        private Acl $acl,
        private User $user,
        private EntityManager $entityManager,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->check('Calendar')) {
            throw new Forbidden();
        }

        $from = $request->getQueryParam('from');
        $to = $request->getQueryParam('to');

        if (empty($from) || empty($to)) {
            throw new BadRequest();
        }

        $userId = $this->user->getId();

        if (!$userId) {
            throw new Forbidden();
        }

        $fromDt = strlen($from) === 10 ? $from . ' 00:00:00' : $from;
        $toDt = strlen($to) === 10 ? $to . ' 23:59:59' : $to;

        $collection = $this->entityManager
            ->getRDBRepository(OverlaySyncRunner::ENTITY_TYPE)
            ->where([
                'userId' => $userId,
                'OR' => [
                    [
                        'dateStart!=' => null,
                        'dateStart>=' => $fromDt,
                        'dateStart<=' => $toDt,
                    ],
                    [
                        'dateStartDate!=' => null,
                        'dateStartDate>=' => substr($fromDt, 0, 10),
                        'dateStartDate<=' => substr($toDt, 0, 10),
                    ],
                ],
            ])
            ->limit(0, 500)
            ->find();

        $list = [];

        foreach ($collection as $entity) {
            $id = (string) $entity->getId();
            $row = [
                'id' => $id,
                'calendarEventKey' => $id,
                'scope' => OverlaySyncRunner::ENTITY_TYPE,
                'name' => (string) $entity->get('name'),
                'status' => null,
            ];

            if ($entity->get('isAllDay') || $entity->get('dateStartDate')) {
                $row['dateStartDate'] = (string) $entity->get('dateStartDate');
                $row['dateEndDate'] = (string) ($entity->get('dateEndDate') ?: $entity->get('dateStartDate'));
            } else {
                $row['dateStart'] = (string) $entity->get('dateStart');
                $row['dateEnd'] = (string) ($entity->get('dateEnd') ?: $entity->get('dateStart'));
            }

            $list[] = $row;
        }

        return ResponseComposer::json($list);
    }
}
