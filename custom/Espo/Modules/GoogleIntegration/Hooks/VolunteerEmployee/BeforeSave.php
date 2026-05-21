<?php

namespace Espo\Modules\GoogleIntegration\Hooks\VolunteerEmployee;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSaveHook<Entity>
 */
class BeforeSave implements BeforeSaveHook
{
    public static int $order = 9;

    public function __construct(
        private DateSourceProvider $dateSourceProvider
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if (
            $entity->get('saveToGoogleCalendar')
            || $entity->isAttributeChanged('googleCalendarDateSourceList')
            || $entity->isAttributeChanged('saveToGoogleCalendar')
        ) {
            $this->normalizeDateSourceList($entity);
        }

        $this->assertDateSourcesWhenExportEnabled($entity);
    }

    private function normalizeDateSourceList(Entity $entity): void
    {
        $allowed = array_values(array_filter(array_map(
            static fn (array $source): string => (string) ($source['sourceDateType'] ?? 'main'),
            $this->dateSourceProvider->getActiveSourcesForEntityType($entity->getEntityType())
        )));

        if ($allowed === []) {
            $entity->set('googleCalendarDateSourceList', []);

            return;
        }

        $selected = $entity->get('googleCalendarDateSourceList');
        $legacyUnset = !is_array($selected);

        if ($legacyUnset) {
            $selected = [];
        }

        $filtered = array_values(array_unique(array_filter(
            array_map('strval', $selected),
            static fn (string $item): bool => in_array($item, $allowed, true)
        )));

        if ($filtered === [] && $entity->get('saveToGoogleCalendar')) {
            $userExplicitlyClearedList = !$legacyUnset
                && $entity->isAttributeChanged('googleCalendarDateSourceList');

            $enablingExportOnly = $entity->isAttributeChanged('saveToGoogleCalendar')
                && $entity->get('saveToGoogleCalendar');

            if (!$userExplicitlyClearedList || $enablingExportOnly) {
                $filtered = [$allowed[0]];
            }
        }

        $entity->set('googleCalendarDateSourceList', $filtered);
    }

    private function assertDateSourcesWhenExportEnabled(Entity $entity): void
    {
        if (!$entity->get('saveToGoogleCalendar')) {
            return;
        }

        $selected = $entity->get('googleCalendarDateSourceList');

        if (!is_array($selected) || $selected === []) {
            throw new BadRequest(
                'Select at least one Google Calendar date when Save in Google Calendar is enabled.'
            );
        }
    }
}
