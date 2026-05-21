<?php

namespace Espo\Modules\GoogleIntegration\Hooks\Call;

use Espo\Core\Hook\Hook\AfterRemove as AfterRemoveHook;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarEntityLifecycle;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * @implements AfterRemoveHook<Entity>
 */
class AfterRemove implements AfterRemoveHook
{
    public static int $order = 20;

    public function __construct(
        private GoogleCalendarEntityLifecycle $googleCalendarEntityLifecycle
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->googleCalendarEntityLifecycle->handleAfterRemove($entity);
    }
}
