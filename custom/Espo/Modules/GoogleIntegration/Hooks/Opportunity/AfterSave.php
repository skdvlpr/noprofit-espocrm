<?php

namespace Espo\Modules\GoogleIntegration\Hooks\Opportunity;

use Espo\Core\Hook\Hook\AfterSave as AfterSaveHook;
use Espo\Core\Utils\Log;
use Espo\Modules\GoogleIntegration\Tools\Calendar\EventPusher;
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
        private Log $log
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        try {
            $this->eventPusher->pushIfRequested($entity);
        } catch (Throwable $e) {
            $this->log->error('Google Calendar Opportunity push failed: ' . $e->getMessage());
        }
    }
}
