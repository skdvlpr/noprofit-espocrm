<?php

namespace Espo\Modules\GoogleIntegration\Hooks\GoogleCalendarOverlayEvent;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Modules\GoogleIntegration\Tools\Calendar\OverlaySyncRunner;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Overlay rows are sync-cache only — block manual create/edit (incl. admin UI).
 *
 * @implements BeforeSaveHook<Entity>
 */
class BeforeSave implements BeforeSaveHook
{
    public static int $order = 1;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(OverlaySyncRunner::SAVE_OPTION_SYNC) === true) {
            return;
        }

        throw new Forbidden('Google calendar overlay events are managed by sync only.');
    }
}
