<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use DateInterval;
use Espo\Core\ApplicationState;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Job\QueueName;
use Espo\Core\Utils\Log;
use Espo\Modules\GoogleIntegration\Jobs\PushGoogleCalendarEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

class GoogleCalendarEntityLifecycle
{
    public function __construct(
        private EventPusher $eventPusher,
        private EventRemover $eventRemover,
        private EntityManager $entityManager,
        private ApplicationState $applicationState,
        private JobSchedulerFactory $jobSchedulerFactory,
        private Log $log
    ) {}

    public function handleAfterSave(Entity $entity): void
    {
        if (
            !$entity->get('saveToGoogleCalendar')
            && $entity->isAttributeChanged('saveToGoogleCalendar')
        ) {
            $user = $this->applicationState->getUser();
            $userId = $user->getId();

            if (is_string($userId) && $userId !== '' && !$user->isApi() && !$user->isSystem()) {
                try {
                    $this->eventRemover->removeAllLinksForEntity($entity, $userId);
                } catch (Throwable $e) {
                    $this->log->error(
                        'Google Calendar unlink failed on '
                        . $entity->getEntityType()
                        . ': '
                        . $e->getMessage()
                    );
                }
            }

            return;
        }

        try {
            $entityForPush = $this->reloadEntityForPush($entity) ?? $entity;
            $this->eventPusher->pushIfRequested($entityForPush);
        } catch (Throwable $e) {
            $this->log->error(
                'Google Calendar push failed on '
                . $entity->getEntityType()
                . ': '
                . $e->getMessage()
            );

            $this->scheduleRetryJob($entity);
        }
    }

    public function handleAfterRemove(Entity $entity): void
    {
        try {
            $this->eventRemover->removeAllLinksForEntity($entity, null);
        } catch (Throwable $e) {
            $this->log->error(
                'Google Calendar delete on remove failed for '
                . $entity->getEntityType()
                . ': '
                . $e->getMessage()
            );
        }
    }

    private function reloadEntityForPush(Entity $entity): ?Entity
    {
        $id = $entity->getId();

        if ($id === null || $id === '') {
            return null;
        }

        return $this->entityManager->getEntityById($entity->getEntityType(), $id);
    }

    private function scheduleRetryJob(Entity $entity): void
    {
        try {
            $user = $this->applicationState->getUser();

            if (!$user->getId() || $user->isSystem() || $user->isApi()) {
                return;
            }

            $this->jobSchedulerFactory
                ->create()
                ->setClassName(PushGoogleCalendarEntity::class)
                ->setQueue(QueueName::E0)
                ->setDelay(new DateInterval('PT30S'))
                ->setData([
                    'entityType' => $entity->getEntityType(),
                    'entityId' => $entity->getId(),
                    'userId' => $user->getId(),
                ])
                ->schedule();
        } catch (Throwable $e) {
            $this->log->error(
                'Google Calendar push job schedule failed for '
                . $entity->getEntityType()
                . ': '
                . $e->getMessage()
            );
        }
    }
}
