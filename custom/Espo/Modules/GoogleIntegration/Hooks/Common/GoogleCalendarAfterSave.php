<?php

namespace Espo\Modules\GoogleIntegration\Hooks\Common;

use Espo\Core\Hook\Hook\AfterSave as AfterSaveHook;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarEntityLifecycle;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements AfterSaveHook<Entity>
 */
class GoogleCalendarAfterSave implements AfterSaveHook
{
    public static int $order = 20;

    public function __construct(
        private GoogleCalendarEntityLifecycle $googleCalendarEntityLifecycle
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        $this->googleCalendarEntityLifecycle->handleAfterSave($entity);
    }
}
