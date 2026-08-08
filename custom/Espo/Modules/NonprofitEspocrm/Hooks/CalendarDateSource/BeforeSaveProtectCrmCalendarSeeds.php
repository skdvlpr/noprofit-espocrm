<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\CalendarDateSource;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseCalendarDateSourceDefaults;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep Safehouse CRM/Google seeds healthy:
 * - ActivityOfferSlot: isActive stays on (Google export optional); calendarViewEnabled
 *   stays off so CRM calendar uses native Espo (no GoogleIntegration dependency).
 * - Other Safehouse seeds: keep both active for CRM calendar via CDS when needed.
 *
 * @implements BeforeSave<Entity>
 */
class BeforeSaveProtectCrmCalendarSeeds implements BeforeSave
{
    public static int $order = 6;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $seed = $this->findSeed($entity);

        if ($seed === null) {
            return;
        }

        $target = (string) ($seed['targetEntityType'] ?? '');

        if ($target === 'ActivityOfferSlot') {
            if ($entity->isAttributeChanged('isActive') && $entity->get('isActive') === false) {
                $entity->set('isActive', true);
            }

            // Native Espo calendar owns CRM display for shifts.
            $entity->set('calendarViewEnabled', false);

            return;
        }

        if ($entity->isAttributeChanged('isActive') && $entity->get('isActive') === false) {
            $entity->set('isActive', true);
        }

        if ($entity->isAttributeChanged('calendarViewEnabled') && $entity->get('calendarViewEnabled') === false) {
            $entity->set('calendarViewEnabled', true);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function findSeed(Entity $entity): ?array
    {
        $target = trim((string) $entity->get('targetEntityType'));
        $sourceDateType = trim((string) $entity->get('sourceDateType'));

        if ($target === '' || $sourceDateType === '') {
            return null;
        }

        foreach (SafehouseCalendarDateSourceDefaults::sources() as $seed) {
            if (
                (string) ($seed['targetEntityType'] ?? '') === $target &&
                (string) ($seed['sourceDateType'] ?? '') === $sourceDateType
            ) {
                return $seed;
            }
        }

        return null;
    }
}
