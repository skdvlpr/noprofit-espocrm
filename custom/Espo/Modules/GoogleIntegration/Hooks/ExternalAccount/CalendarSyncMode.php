<?php

namespace Espo\Modules\GoogleIntegration\Hooks\ExternalAccount;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\GoogleIntegration\Tools\Calendar\SyncMode;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Normalizes per-user calendarSyncMode on Google ExternalAccount rows.
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

        $mode = $entity->get('calendarSyncMode');

        if ($mode === null || $mode === '') {
            $entity->set('calendarSyncMode', SyncMode::DEFAULT);

            return;
        }

        if (!SyncMode::isValid(is_string($mode) ? $mode : null)) {
            throw new BadRequest('Invalid calendarSyncMode.');
        }
    }
}
