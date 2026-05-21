<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\ApplicationState;
use Espo\Core\Utils\Log;
use Espo\ORM\Entity;
use Throwable;

/**
 * Shared afterSave / afterRemove handling for calendar-capable CRM entities.
 */
class GoogleCalendarEntityLifecycle
{
    public function __construct(
        private EventPusher $eventPusher,
        private EventRemover $eventRemover,
        private ApplicationState $applicationState,
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
            $this->eventPusher->pushIfRequested($entity);
        } catch (Throwable $e) {
            $this->log->error(
                'Google Calendar push failed on '
                . $entity->getEntityType()
                . ': '
                . $e->getMessage()
            );
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
}
