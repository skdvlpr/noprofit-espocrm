<?php

namespace Espo\Modules\GoogleIntegration\Hooks\Common;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\GoogleCalendarExportGuard;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSaveHook<Entity>
 */
class PerDateGoogleCalendarBeforeSave implements BeforeSaveHook
{
    public static int $order = 9;

    public function __construct(
        private DateSourceProvider $dateSourceProvider,
        private GoogleCalendarExportGuard $googleCalendarExportGuard,
        private ManagerCalendarShare $managerCalendarShare,
        private User $user,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $this->googleCalendarExportGuard->assertExportAllowed($entity);
        $this->assertShareTargetsAllowed($entity);

        if ($this->shouldNormalizeDateSourceList($entity)) {
            $this->normalizeDateSourceList($entity);
        }

        if ($entity->get('saveToGoogleCalendar')) {
            $this->syncEventSettingsWithDateList($entity);
        }

        $this->assertDateSourcesWhenExportEnabled($entity);
    }

    private function assertShareTargetsAllowed(Entity $entity): void
    {
        $hasUsers = $this->managerCalendarShare->readLinkMultipleIds(
            $entity,
            ManagerCalendarShare::FIELD_USERS
        ) !== [];
        $hasTeams = $this->managerCalendarShare->readLinkMultipleIds(
            $entity,
            ManagerCalendarShare::FIELD_TEAMS
        ) !== [];

        if (!$hasUsers && !$hasTeams) {
            return;
        }

        if ($this->managerCalendarShare->actorCanShare($this->user)) {
            return;
        }

        if ($entity->hasRelation(ManagerCalendarShare::FIELD_USERS)) {
            $entity->setLinkMultipleIdList(ManagerCalendarShare::FIELD_USERS, []);
        }

        if ($entity->hasRelation(ManagerCalendarShare::FIELD_TEAMS)) {
            $entity->setLinkMultipleIdList(ManagerCalendarShare::FIELD_TEAMS, []);
        }

        $entity->set('googleCalendarShareRoutingMode', 'primary');
        $entity->set('googleCalendarShareCalendarUserId', null);
        $entity->set('googleCalendarShareCalendarId', 'primary');
    }

    private function shouldNormalizeDateSourceList(Entity $entity): bool
    {
        if (
            $entity->get('saveToGoogleCalendar')
            || $entity->isAttributeChanged('googleCalendarDateSourceList')
            || $entity->isAttributeChanged('saveToGoogleCalendar')
            || $entity->isAttributeChanged('googleCalendarEventSettings')
        ) {
            return true;
        }

        foreach ($this->dateSourceProvider->getActiveSourcesForEntityType($entity->getEntityType()) as $source) {
            $field = $source['dateField'] ?? null;

            if (is_string($field) && $field !== '' && $entity->isAttributeChanged($field)) {
                return true;
            }
        }

        return false;
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

    /**
     * Ensure a settings row exists for every selected date (e.g. user adds closeDate on edit).
     */
    private function syncEventSettingsWithDateList(Entity $entity): void
    {
        $selected = $entity->get('googleCalendarDateSourceList');

        if (!is_array($selected) || $selected === []) {
            return;
        }

        $rows = $entity->get('googleCalendarEventSettings');
        $byType = [];

        if (is_array($rows)) {
            foreach ($rows as $row) {
                if (is_object($row)) {
                    $row = get_object_vars($row);
                }

                if (!is_array($row)) {
                    continue;
                }

                $type = (string) ($row['sourceDateType'] ?? '');

                if ($type !== '') {
                    $byType[$type] = $row;
                }
            }
        }

        $merged = [];

        foreach ($selected as $sourceDateType) {
            $sourceDateType = (string) $sourceDateType;

            $merged[] = $byType[$sourceDateType] ?? [
                'sourceDateType' => $sourceDateType,
                'reminderMode' => 'none',
                'reminders' => [],
                'location' => '',
                'visibility' => 'default',
                'transparency' => 'opaque',
                'colorId' => '',
                'calendarTemplateId' => '',
                'descriptionTemplateOverride' => '',
            ];
        }

        $entity->set('googleCalendarEventSettings', $merged);
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
