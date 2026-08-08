<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\CalendarDateSource;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseCalendarDateSourceDefaults;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep Safehouse CRM-calendar seeds active + visible. Users hide entities via
 * the Calendar view filter, not by disabling the date source.
 *
 * @implements BeforeSave<Entity>
 */
class BeforeSaveProtectCrmCalendarSeeds implements BeforeSave
{
    public static int $order = 6;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (!$this->isSafehouseSeed($entity)) {
            return;
        }

        // Soft-deleted rows cannot be "saved" back here; remove is blocked separately.
        if ($entity->isAttributeChanged('isActive') && $entity->get('isActive') === false) {
            $entity->set('isActive', true);
        }

        if ($entity->isAttributeChanged('calendarViewEnabled') && $entity->get('calendarViewEnabled') === false) {
            $entity->set('calendarViewEnabled', true);
        }
    }

    private function isSafehouseSeed(Entity $entity): bool
    {
        $target = trim((string) $entity->get('targetEntityType'));
        $sourceDateType = trim((string) $entity->get('sourceDateType'));

        if ($target === '' || $sourceDateType === '') {
            return false;
        }

        foreach (SafehouseCalendarDateSourceDefaults::sources() as $seed) {
            if (
                (string) ($seed['targetEntityType'] ?? '') === $target &&
                (string) ($seed['sourceDateType'] ?? '') === $sourceDateType
            ) {
                return true;
            }
        }

        return false;
    }
}
