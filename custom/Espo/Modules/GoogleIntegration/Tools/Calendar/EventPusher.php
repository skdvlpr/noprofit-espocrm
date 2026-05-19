<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\Error;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Htmlizer\TemplateRendererFactory;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class EventPusher
{
    private const LINK_ENTITY_TYPE = 'GoogleCalendarEventLink';
    private const DEFAULT_CALENDAR_ID = 'primary';
    private const MAIN_DATE_TYPE = 'main';
    private const OPPORTUNITY_DATE_TYPES = ['presentationDate', 'closeDate'];
    private const MAX_REMINDERS = 5;
    private const MAX_REMINDER_MINUTES = 40320;

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private TemplateRendererFactory $templateRendererFactory,
        private Config $config,
        private Metadata $metadata,
        private User $user,
        private Log $log,
        private DateSourceProvider $dateSourceProvider,
        private CalendarTemplateApplier $calendarTemplateApplier
    ) {}

    public function pushIfRequested(Entity $entity): void
    {
        if (!$entity->get('saveToGoogleCalendar')) {
            return;
        }

        if (!$this->user->getId() || $this->user->isApi()) {
            return;
        }

        $events = $this->buildGoogleEvents($entity);

        if ($events === []) {
            $this->log->warning(
                'Google Calendar sync skipped: no supported date fields for '
                . $entity->getEntityType() . ' ' . $entity->getId()
            );

            if ($entity->getEntityType() !== 'Opportunity') {
                return;
            }
        }

        $client = $this->clientManager->create(Installer::INTEGRATION_ID, $this->user->getId());

        if (!$client instanceof GoogleClient) {
            $this->log->warning(
                'Google Calendar sync skipped: Google account is not connected for user ' . $this->user->getId()
            );

            return;
        }

        $syncedDateTypes = [];

        foreach ($events as $item) {
            $this->pushEvent(
                $client,
                $entity,
                $item['sourceDateType'],
                $item['event'],
                $item['calendarId'] ?? self::DEFAULT_CALENDAR_ID
            );
            $syncedDateTypes[] = $item['sourceDateType'];
        }

        if ($entity->getEntityType() === 'Opportunity') {
            $this->removeStaleOpportunityLinks($client, $entity, self::DEFAULT_CALENDAR_ID, $syncedDateTypes);
        }
    }

    /**
     * @param array<string, mixed> $event
     */
    private function pushEvent(
        GoogleClient $client,
        Entity $entity,
        string $sourceDateType,
        array $event,
        string $calendarId
    ): void {
        $link = $this->findLink($entity, $sourceDateType);

        if ($link !== null && is_string($link->get('googleEventId')) && $link->get('googleEventId') !== '') {
            try {
                $result = $client->updateCalendarEvent($link->get('googleEventId'), $event, $calendarId);
            } catch (Error $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }

                $result = $client->createCalendarEvent($event, $calendarId);
            }
        } else {
            $result = $client->createCalendarEvent($event, $calendarId);
        }

        $googleEventId = $result['id'] ?? null;

        if (!is_string($googleEventId) || $googleEventId === '') {
            throw new Error('Google Calendar sync failed: missing Google event id.');
        }

        $this->saveLink($entity, $sourceDateType, $calendarId, $googleEventId, $result['htmlLink'] ?? null);
    }

    private function findLink(Entity $entity, string $sourceDateType): ?Entity
    {
        $link = $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'sourceDateType' => $sourceDateType,
                'userId' => $this->user->getId(),
                'deleted' => false,
            ])
            ->findOne();

        if ($link !== null) {
            return $link;
        }

        if ($entity->getEntityType() !== 'Opportunity' || $sourceDateType !== 'closeDate') {
            return null;
        }

        // Existing rows before two-date Opportunity support represented closeDate as the only event.
        $mainLink = $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'sourceDateType' => self::MAIN_DATE_TYPE,
                'userId' => $this->user->getId(),
                'deleted' => false,
            ])
            ->findOne();

        if ($mainLink !== null) {
            return $mainLink;
        }

        return $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'sourceDateType' => null,
                'userId' => $this->user->getId(),
                'deleted' => false,
            ])
            ->findOne();
    }

    private function saveLink(
        Entity $entity,
        string $sourceDateType,
        string $calendarId,
        string $googleEventId,
        mixed $htmlLink
    ): void {
        $link = $this->findLink($entity, $sourceDateType);

        if ($link === null) {
            $link = $this->entityManager->getNewEntity(self::LINK_ENTITY_TYPE);
            $link->set([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'sourceDateType' => $sourceDateType,
                'userId' => $this->user->getId(),
            ]);
        }

        $link->set([
            'name' => $entity->getEntityType() . ':' . $entity->getId() . ':' . $sourceDateType . ':' . $this->user->getId(),
            'sourceDateType' => $sourceDateType,
            'calendarId' => $calendarId,
            'googleEventId' => $googleEventId,
            'googleEventHtmlLink' => is_string($htmlLink) ? $htmlLink : null,
            'lastSyncedAt' => gmdate('Y-m-d H:i:s'),
        ]);

        $this->entityManager->saveEntity($link, [
            SaveOption::SKIP_HOOKS => true,
            SaveOption::SILENT => true,
        ]);
    }

    /**
     * @param array<int, string> $activeDateTypes
     */
    private function removeStaleOpportunityLinks(
        GoogleClient $client,
        Entity $entity,
        string $calendarId,
        array $activeDateTypes
    ): void {
        $links = $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'userId' => $this->user->getId(),
                'deleted' => false,
            ])
            ->find();

        foreach ($links as $link) {
            $sourceDateType = (string) ($link->get('sourceDateType') ?? '');
            $effectiveDateType = in_array($sourceDateType, ['', self::MAIN_DATE_TYPE], true)
                ? 'closeDate'
                : $sourceDateType;

            if (
                !in_array($effectiveDateType, self::OPPORTUNITY_DATE_TYPES, true)
                || in_array($effectiveDateType, $activeDateTypes, true)
            ) {
                continue;
            }

            $googleEventId = $link->get('googleEventId');

            if (is_string($googleEventId) && $googleEventId !== '') {
                try {
                    $client->deleteCalendarEvent($googleEventId, $calendarId);
                } catch (Error $e) {
                    if ($e->getCode() !== 404) {
                        throw $e;
                    }
                }
            }

            $this->entityManager->removeEntity($link);
        }
    }

    /**
     * @return array<int, array{sourceDateType: string, event: array<string, mixed>, calendarId?: string}>
     */
    private function buildGoogleEvents(Entity $entity): array
    {
        if ($entity->getEntityType() === 'Opportunity') {
            return $this->buildOpportunityGoogleEvents($entity);
        }

        $source = $this->dateSourceProvider->getActiveSourcesForEntityType($entity->getEntityType())[0] ?? null;
        $dateRange = $source !== null ? $this->buildDateRangeFromSource($entity, $source) : $this->buildDateRange($entity);

        if ($dateRange === null) {
            return [];
        }

        $settings = $this->buildTemplateSettings(
            $entity,
            self::MAIN_DATE_TYPE,
            $this->getEntityCalendarTemplateId($entity),
            []
        );

        return [[
            'sourceDateType' => self::MAIN_DATE_TYPE,
            'event' => $this->buildGoogleEvent($entity, $dateRange, self::MAIN_DATE_TYPE, $settings),
            'calendarId' => $this->resolveCalendarId($entity, $settings),
        ]];
    }

    /**
     * @param ?array<string, mixed> $settings
     */
    private function resolveCalendarId(Entity $entity, ?array $settings): string
    {
        $entityCalendarId = trim((string) ($entity->get('googleCalendarId') ?? ''));

        if ($entityCalendarId !== '') {
            return $entityCalendarId;
        }

        $settingsCalendarId = trim((string) ($settings['calendarId'] ?? ''));

        if ($settingsCalendarId !== '') {
            return $settingsCalendarId;
        }

        return self::DEFAULT_CALENDAR_ID;
    }

    /**
     * @param array{start: array<string, string>, end: array<string, string>} $dateRange
     * @return array<string, mixed>
     */
    /**
     * @param ?array<string, mixed> $settings
     */
    private function buildGoogleEvent(Entity $entity, array $dateRange, string $sourceDateType, ?array $settings): array
    {
        $event = [
            'summary' => trim((string) ($settings['summary'] ?? '')) ?: $this->buildSummary($entity, $sourceDateType),
            'description' => $this->buildDescription($entity, $sourceDateType, $settings),
            'start' => $dateRange['start'],
            'end' => $dateRange['end'],
            'reminders' => $this->buildReminders($entity, $settings),
            'extendedProperties' => [
                'private' => [
                    'espocrmEntityType' => $entity->getEntityType(),
                    'espocrmEntityId' => (string) $entity->getId(),
                    'espocrmSourceDateType' => $sourceDateType,
                    'espocrmUserId' => (string) $this->user->getId(),
                ],
            ],
        ];

        $location = trim((string) ($settings['location'] ?? $entity->get('googleCalendarLocation') ?? ''));

        if ($location !== '') {
            $event['location'] = $location;
        }

        $visibility = $settings['visibility'] ?? $entity->get('googleCalendarVisibility');

        if (in_array($visibility, ['default', 'private', 'public'], true)) {
            $event['visibility'] = $visibility;
        }

        $transparency = $settings['transparency'] ?? $entity->get('googleCalendarTransparency');

        if (in_array($transparency, ['opaque', 'transparent'], true)) {
            $event['transparency'] = $transparency;
        }

        $colorId = is_string($settings['colorId'] ?? null)
            ? trim($settings['colorId'])
            : trim((string) ($entity->get('googleCalendarColorId') ?? ''));

        if ($colorId === '') {
            // Empty is the UI value for Google default color; the API represents it by omitting colorId.
            unset($event['colorId']);
        } else if (preg_match('/^(?:[1-9]|10|11)$/', $colorId)) {
            $event['colorId'] = $colorId;
        }

        return $event;
    }

    /**
     * @return array<int, array{sourceDateType: string, event: array<string, mixed>, calendarId?: string}>
     */
    private function buildOpportunityGoogleEvents(Entity $entity): array
    {
        $events = [];
        $sources = [];

        foreach ($this->dateSourceProvider->getActiveSourcesForEntityType('Opportunity') as $source) {
            $sources[(string) ($source['sourceDateType'] ?? '')] = $source;
        }

        foreach ($this->getOpportunityDateTypes($entity) as $sourceDateType) {
            $source = $sources[$sourceDateType] ?? null;
            $dateRange = $source !== null
                ? $this->buildDateRangeFromSource($entity, $source)
                : $this->buildAllDayRange($entity->get($sourceDateType));

            if ($dateRange === null) {
                continue;
            }

            $settings = $this->getOpportunityEventSettings($entity, $sourceDateType);

            $events[] = [
                'sourceDateType' => $sourceDateType,
                'event' => $this->buildGoogleEvent($entity, $dateRange, $sourceDateType, $settings),
                'calendarId' => $this->resolveCalendarId($entity, $settings),
            ];
        }

        return $events;
    }

    /**
     * @return array<int, string>
     */
    private function getOpportunityDateTypes(Entity $entity): array
    {
        $selected = $entity->get('googleCalendarOpportunityDateList');

        if (!is_array($selected)) {
            $selected = ['closeDate'];
        }

        return array_values(array_filter(
            array_unique(array_map('strval', $selected)),
            static fn (string $item): bool => in_array($item, self::OPPORTUNITY_DATE_TYPES, true)
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function getOpportunityEventSettings(Entity $entity, string $sourceDateType): array
    {
        $rows = $entity->get('googleCalendarOpportunityEventSettings');

        if (!is_array($rows)) {
            $rows = [];
        }

        foreach ($rows as $row) {
            $row = $this->normalizeArrayRow($row);

            if (($row['sourceDateType'] ?? null) === $sourceDateType) {
                return $this->buildTemplateSettings(
                    $entity,
                    $sourceDateType,
                    is_string($row['calendarTemplateId'] ?? null) ? $row['calendarTemplateId'] : null,
                    $row
                );
            }
        }

        return $this->buildTemplateSettings($entity, $sourceDateType, null, [
            'sourceDateType' => $sourceDateType,
            'reminderMode' => $entity->get('googleCalendarReminderMode') ?: 'none',
            'reminders' => $entity->get('googleCalendarReminders') ?: [],
            'location' => $entity->get('googleCalendarLocation') ?: '',
            'visibility' => $entity->get('googleCalendarVisibility') ?: 'default',
            'transparency' => $entity->get('googleCalendarTransparency') ?: 'opaque',
            'colorId' => $entity->get('googleCalendarColorId') ?: '',
            'descriptionTemplateOverride' => $entity->get('googleCalendarDescriptionTemplateOverride') ?: '',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeArrayRow(mixed $row): array
    {
        if (is_object($row)) {
            $row = get_object_vars($row);
        }

        return is_array($row) ? $row : [];
    }

    /**
     * @return array{start: array<string, string>, end: array<string, string>}|null
     */
    private function buildDateRange(Entity $entity): ?array
    {
        return match ($entity->getEntityType()) {
            'Meeting', 'Call' => $this->buildDateTimeRange($entity->get('dateStart'), $entity->get('dateEnd')),
            'Task' => $this->buildTaskDateRange($entity),
            default => null,
        };
    }

    /**
     * @param array<string, mixed> $source
     * @return array{start: array<string, string>, end: array<string, string>}|null
     */
    private function buildDateRangeFromSource(Entity $entity, array $source): ?array
    {
        $dateField = (string) ($source['dateField'] ?? '');

        if ($dateField === '') {
            return null;
        }

        if (!empty($source['allDay'])) {
            return $this->buildAllDayRange($entity->get($dateField));
        }

        $endDateField = (string) ($source['endDateField'] ?? '');

        return $this->buildDateTimeRange(
            $entity->get($dateField),
            $endDateField !== '' ? $entity->get($endDateField) : $entity->get($dateField)
        );
    }

    /**
     * @return array{start: array<string, string>, end: array<string, string>}|null
     */
    private function buildTaskDateRange(Entity $entity): ?array
    {
        $end = $entity->get('dateEnd');

        if (!$end) {
            return null;
        }

        return $this->buildDateTimeRange($entity->get('dateStart') ?: $end, $end);
    }

    /**
     * @return array{start: array<string, string>, end: array<string, string>}|null
     */
    private function buildDateTimeRange(mixed $startValue, mixed $endValue): ?array
    {
        if (!is_string($startValue) || $startValue === '' || !is_string($endValue) || $endValue === '') {
            return null;
        }

        if ($this->isDateOnly($startValue) && $this->isDateOnly($endValue)) {
            $end = (new DateTimeImmutable($endValue))->add(new DateInterval('P1D'));

            return [
                'start' => ['date' => $startValue],
                'end' => ['date' => $end->format('Y-m-d')],
            ];
        }

        $start = $this->parseDateTime($startValue);
        $end = $this->parseDateTime($endValue);

        if ($end <= $start) {
            $end = $start->add(new DateInterval('PT30M'));
        }

        return [
            'start' => ['dateTime' => $start->format(DATE_RFC3339)],
            'end' => ['dateTime' => $end->format(DATE_RFC3339)],
        ];
    }

    /**
     * @return array{start: array<string, string>, end: array<string, string>}|null
     */
    private function buildAllDayRange(mixed $dateValue): ?array
    {
        if (!is_string($dateValue) || !$this->isDateOnly($dateValue)) {
            return null;
        }

        $end = (new DateTimeImmutable($dateValue))->add(new DateInterval('P1D'));

        return [
            'start' => ['date' => $dateValue],
            'end' => ['date' => $end->format('Y-m-d')],
        ];
    }

    private function parseDateTime(string $value): DateTimeImmutable
    {
        $normalized = str_replace(' ', 'T', $value);

        if (!str_contains($normalized, '+') && !str_ends_with($normalized, 'Z')) {
            $normalized .= 'Z';
        }

        return new DateTimeImmutable($normalized, new DateTimeZone('UTC'));
    }

    private function isDateOnly(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value);
    }

    private function buildSummary(Entity $entity, string $sourceDateType): string
    {
        $name = trim((string) ($entity->get('name') ?? ''));

        if ($entity->getEntityType() === 'Opportunity') {
            $dateLabel = match ($sourceDateType) {
                'presentationDate' => 'Presentation date',
                'closeDate' => 'Close date',
                default => '',
            };

            return trim(
                'Opportunity: '
                . ($name !== '' ? $name : $entity->getId())
                . ($dateLabel !== '' ? ' - ' . $dateLabel : '')
            );
        }

        return $name !== '' ? $name : $entity->getEntityType() . ' ' . $entity->getId();
    }

    /**
     * @param ?array<string, mixed> $settings
     */
    private function buildDescription(Entity $entity, string $sourceDateType, ?array $settings): string
    {
        if (is_array($settings) && is_string($settings['description'] ?? null) && trim($settings['description']) !== '') {
            return trim($settings['description']);
        }

        $template = $this->resolveRelatedTemplateVariables(
            $entity,
            $this->getDescriptionTemplate($entity, $settings)
        );

        return trim($this->templateRendererFactory
            ->create()
            ->setEntity($entity)
            ->setUser($this->user)
            ->setData([
                'espocrmUrl' => $this->buildRecordUrl($entity),
                'sourceDateType' => $sourceDateType,
            ])
            ->setTemplate($template)
            ->render());
    }

    /**
     * @param ?array<string, mixed> $settings
     * @return array{useDefault: bool, overrides: array<int, array{method: string, minutes: int}>}
     */
    private function buildReminders(Entity $entity, ?array $settings): array
    {
        $mode = $settings['reminderMode'] ?? $entity->get('googleCalendarReminderMode');

        if ($mode === 'default') {
            return [
                'useDefault' => true,
                'overrides' => [],
            ];
        }

        if ($mode !== 'custom') {
            return [
                'useDefault' => false,
                'overrides' => [],
            ];
        }

        return [
            'useDefault' => false,
            'overrides' => $this->buildCustomReminderOverrides($entity, $settings),
        ];
    }

    /**
     * @param ?array<string, mixed> $settings
     * @return array<int, array{method: string, minutes: int}>
     */
    private function buildCustomReminderOverrides(Entity $entity, ?array $settings): array
    {
        $rows = $settings['reminders'] ?? $entity->get('googleCalendarReminders');

        if (!is_array($rows)) {
            return [];
        }

        $overrides = [];

        foreach (array_slice($rows, 0, self::MAX_REMINDERS) as $row) {
            $row = $this->normalizeArrayRow($row);

            if ($row === []) {
                continue;
            }

            $minutes = $this->reminderRowToMinutes($row);

            if ($minutes < 0 || $minutes > self::MAX_REMINDER_MINUTES) {
                continue;
            }

            $method = $row['method'] ?? 'popup';

            $overrides[] = [
                'method' => $method === 'email' ? 'email' : 'popup',
                'minutes' => $minutes,
            ];
        }

        return $overrides;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function reminderRowToMinutes(array $row): int
    {
        $amount = max(0, (int) ($row['amount'] ?? 0));

        return match ($row['unit'] ?? 'days') {
            'weeks' => $amount * 7 * 24 * 60,
            'days' => $amount * 24 * 60,
            'hours' => $amount * 60,
            default => $amount,
        };
    }

    /**
     * @param ?array<string, mixed> $settings
     */
    private function getDescriptionTemplate(Entity $entity, ?array $settings): string
    {
        $override = trim((string) ($settings['descriptionTemplateOverride'] ?? ''));

        if ($override !== '') {
            return $override;
        }

        $override = trim((string) ($entity->get('googleCalendarDescriptionTemplateOverride') ?? ''));

        if ($override !== '') {
            return $override;
        }

        $field = 'googleCalendarDescriptionTemplate' . $entity->getEntityType();
        $integration = $this->entityManager->getEntityById('Integration', Installer::INTEGRATION_ID);
        $template = $integration?->get($field);

        if (is_string($template) && trim($template) !== '') {
            return $template;
        }

        $default = $this->metadata->get(['integrations', Installer::INTEGRATION_ID, 'fields', $field, 'default']);

        return is_string($default) && trim($default) !== '' ? $default : '{{name}}';
    }

    private function getEntityCalendarTemplateId(Entity $entity): ?string
    {
        $id = $entity->get('googleCalendarTemplateId');

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * @param array<string, mixed> $recordSettings
     * @return array<string, mixed>
     */
    private function buildTemplateSettings(
        Entity $entity,
        string $sourceDateType,
        ?string $templateId,
        array $recordSettings
    ): array {
        $templateSettings = $this->calendarTemplateApplier->apply($templateId, $entity, $sourceDateType);

        return array_merge($templateSettings, array_filter(
            $recordSettings,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        ));
    }

    private function resolveRelatedTemplateVariables(Entity $entity, string $template): string
    {
        $template = preg_replace_callback(
            '/{{\s*([A-Za-z][A-Za-z0-9_]*)\.([A-Za-z][A-Za-z0-9_]*)\s*}}/',
            function (array $matches) use ($entity): string {
                return $this->escapeTemplateValue(
                    $this->getRelatedScalarValue($entity, $matches[1], $matches[2])
                );
            },
            $template
        );

        return preg_replace_callback(
            '/{{\s*([A-Za-z][A-Za-z0-9_]*)\s*}}/',
            function (array $matches) use ($entity): string {
                $field = $this->metadata->get([
                    'entityDefs',
                    $entity->getEntityType(),
                    'fields',
                    $matches[1],
                ]);

                if (!is_array($field) || !in_array($field['type'] ?? null, ['link', 'linkMultiple', 'linkParent'], true)) {
                    return $matches[0];
                }

                return $this->escapeTemplateValue(
                    $this->getRelatedScalarValue($entity, $matches[1], 'name')
                );
            },
            $template
        );
    }

    private function getRelatedScalarValue(Entity $entity, string $relation, string $field): string
    {
        if (!in_array($relation, $entity->getRelationList(), true)) {
            return '';
        }

        try {
            $relationType = $entity->getRelationType($relation);
            $relationRepository = $this->entityManager->getRelation($entity, $relation);

            if (in_array($relationType, [Entity::BELONGS_TO, Entity::BELONGS_TO_PARENT, Entity::HAS_ONE], true)) {
                $related = $relationRepository->findOne();

                return $related ? $this->stringifyScalar($related->get($field)) : '';
            }

            if (in_array($relationType, [Entity::HAS_MANY, Entity::MANY_MANY, Entity::HAS_CHILDREN], true)) {
                $collection = $relationRepository
                    ->limit(0, 20)
                    ->find();

                $values = [];

                foreach ($collection as $related) {
                    $value = $this->stringifyScalar($related->get($field));

                    if ($value !== '') {
                        $values[] = $value;
                    }
                }

                return implode(', ', $values);
            }
        } catch (\Throwable $e) {
            $this->log->warning(
                'Google Calendar template related variable skipped: '
                . $entity->getEntityType() . '.' . $relation . '.' . $field . ' - ' . $e->getMessage()
            );
        }

        return '';
    }

    private function stringifyScalar(mixed $value): string
    {
        if ($value === null) {
            return '';
        }

        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function escapeTemplateValue(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }

    private function buildRecordUrl(Entity $entity): string
    {
        $siteUrl = rtrim((string) ($this->config->get('siteUrl') ?? ''), '/');

        if ($siteUrl === '') {
            return '';
        }

        return $siteUrl . '/#' . $entity->getEntityType() . '/view/' . $entity->getId();
    }
}
