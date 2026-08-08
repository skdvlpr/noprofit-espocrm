<?php

namespace Espo\Modules\GoogleIntegration\Hooks\ExternalAccount;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\GoogleIntegration\Tools\Calendar\SyncMode;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Force calendarSyncMode=none (manual export only) on Google ExternalAccount rows.
 * Background bidirectional / pull / push modes are intentionally disabled product-wide.
 *
 * @implements BeforeSave<Entity>
 */
class CalendarSyncMode implements BeforeSave
{
    public static int $order = 9;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $id = $entity->getId();

        if (!is_string($id) || !str_starts_with($id, Installer::INTEGRATION_ID . '__')) {
            return;
        }

        $entity->set('calendarSyncMode', SyncMode::NONE);
    }
}
