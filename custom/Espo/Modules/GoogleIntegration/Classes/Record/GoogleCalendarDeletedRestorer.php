<?php

namespace Espo\Modules\GoogleIntegration\Classes\Record;

use Espo\Core\Record\Deleted\DefaultRestorer;
use Espo\Core\Record\Deleted\Restorer;
use Espo\Core\Utils\Log;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarEntityLifecycle;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Extends DefaultRestorer to re-push Google Calendar events when a record is restored (Ripristina).
 *
 * @implements Restorer<Entity>
 */
class GoogleCalendarDeletedRestorer implements Restorer
{
    public function __construct(
        private DefaultRestorer $defaultRestorer,
        private EntityManager $entityManager,
        private EventPusher $eventPusher,
        private Log $log
    ) {}

    public function restore(Entity $entity): void
    {
        $this->defaultRestorer->restore($entity);

        $this->entityManager->refreshEntity($entity);

        $fresh = $this->entityManager->getEntityById($entity->getEntityType(), $entity->getId());

        if ($fresh === null) {
            return;
        }

        if (!$fresh->get('saveToGoogleCalendar')) {
            return;
        }

        try {
            $this->eventPusher->pushIfRequested($fresh);

            $this->log->info(
                'Google Calendar re-push on restore succeeded for '
                . $fresh->getEntityType() . '/' . $fresh->getId()
            );
        } catch (\Throwable $e) {
            $this->log->error(
                'Google Calendar re-push on restore failed for '
                . $fresh->getEntityType() . '/' . $fresh->getId()
                . ': ' . $e->getMessage()
            );
        }
    }
}
