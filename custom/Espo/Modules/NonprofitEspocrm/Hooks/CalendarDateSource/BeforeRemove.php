<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\CalendarDateSource;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeRemove as BeforeRemoveHook;
use Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseCalendarDateSourceDefaults;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * Volunteer shifts (and other Safehouse CDS seeds) must stay available to the
 * Espo CRM calendar. Deleting the CDS row would hide them from Calendar even
 * though Google OAuth is unrelated — block remove instead.
 *
 * @implements BeforeRemoveHook<Entity>
 */
class BeforeRemove implements BeforeRemoveHook
{
    public static int $order = 5;

    /**
     * @throws Forbidden
     */
    public function beforeRemove(Entity $entity, RemoveOptions $options): void
    {
        $target = trim((string) $entity->get('targetEntityType'));
        $sourceDateType = trim((string) $entity->get('sourceDateType'));

        if ($target === '' || $sourceDateType === '') {
            return;
        }

        foreach (SafehouseCalendarDateSourceDefaults::sources() as $seed) {
            $seedTarget = (string) ($seed['targetEntityType'] ?? '');
            $seedType = (string) ($seed['sourceDateType'] ?? '');

            if ($seedTarget === $target && $seedType === $sourceDateType) {
                throw new Forbidden(
                    'This calendar date source is required for the CRM calendar ' .
                    '(Safehouse). Hide the entity from the Calendar view filters instead of deleting the source.'
                );
            }
        }
    }
}
