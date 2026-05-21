<?php

namespace Espo\Modules\GoogleIntegration\Jobs;

use Espo\Core\Job\Job as JobContract;
use Espo\Core\Job\Job\Data;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Retries Google Calendar export using the CRM user who saved the record (OAuth on External Account).
 */
class PushGoogleCalendarEntity implements JobContract
{
    public function __construct(
        private EntityManager $entityManager,
        private EventPusher $eventPusher
    ) {}

    public function run(Data $data): void
    {
        $entityType = $data->get('entityType');
        $entityId = $data->get('entityId');
        $userId = $data->get('userId');

        if (!is_string($entityType) || $entityType === '' || !is_string($entityId) || $entityId === '') {
            return;
        }

        if (!is_string($userId) || $userId === '') {
            return;
        }

        $entity = $this->entityManager->getEntityById($entityType, $entityId);

        if ($entity === null || !$entity->get('saveToGoogleCalendar')) {
            return;
        }

        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if ($user === null) {
            return;
        }

        try {
            $this->eventPusher->pushIfRequested($entity, $user);
        } catch (Throwable) {
            // Logged inside EventPusher callers when invoked from hooks; keep job quiet.
        }
    }
}
