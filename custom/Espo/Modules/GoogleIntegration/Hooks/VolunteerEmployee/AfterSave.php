<?php

namespace Espo\Modules\GoogleIntegration\Hooks\VolunteerEmployee;

use DateInterval;
use Espo\Core\ApplicationState;
use Espo\Core\Hook\Hook\AfterSave as AfterSaveHook;
use Espo\Core\Job\JobSchedulerFactory;
use Espo\Core\Job\QueueName;
use Espo\Core\Utils\Log;
use Espo\Modules\GoogleIntegration\Jobs\PushGoogleCalendarEntity;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventRemover;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;
use Throwable;

/**
 * @implements AfterSaveHook<Entity>
 */
class AfterSave implements AfterSaveHook
{
    public static int $order = 20;

    public function __construct(
        private EventPusher $eventPusher,
        private EventRemover $eventRemover,
        private Log $log,
        private JobSchedulerFactory $jobSchedulerFactory,
        private ApplicationState $applicationState
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
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
                        'Google Calendar VolunteerEmployee unlink failed: ' . $e->getMessage()
                    );
                }
            }

            return;
        }

        try {
            $this->eventPusher->pushIfRequested($entity);
        } catch (Throwable $e) {
            $this->log->error('Google Calendar VolunteerEmployee push failed: ' . $e->getMessage());

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
            } catch (Throwable $e2) {
                $this->log->error('Google Calendar VolunteerEmployee push job schedule failed: ' . $e2->getMessage());
            }
        }
    }
}
