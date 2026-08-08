<?php

namespace Espo\Modules\GoogleIntegration\Hooks\GoogleCalendarOverlayEvent;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeRemove as BeforeRemoveHook;
use Espo\Modules\GoogleIntegration\Tools\Calendar\OverlaySyncRunner;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * @implements BeforeRemoveHook<Entity>
 */
class BeforeRemove implements BeforeRemoveHook
{
    public static int $order = 1;

    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        if ($options->get(OverlaySyncRunner::SAVE_OPTION_SYNC) === true) {
            return;
        }

        throw new Forbidden('Google calendar overlay events are managed by sync only.');
    }
}
