<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\AclManager;
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
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

class EventPusher
{
    private const LINK_ENTITY_TYPE = 'GoogleCalendarEventLink';
    private const DEFAULT_CALENDAR_ID = 'primary';
    private const MAIN_DATE_TYPE = 'main';
    private const MAX_REMINDERS = 5;
    private const MAX_REMINDER_MINUTES = 40320;

    /** @var User|null When set, Google API calls use this user (e.g. async job after failed HTTP push). */
    private ?User $pushUserOverride = null;

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private TemplateRendererFactory $templateRendererFactory,
        private Config $config,
        private Metadata $metadata,
        private User $sessionUser,
        private Log $log,
        private DateSourceProvider $dateSourceProvider,
        private CalendarTemplateApplier $calendarTemplateApplier,
        private IntegrationState $integrationState,
        private AclManager $aclManager,
        private EventRemover $eventRemover
    ) {}

    public function pushIfRequested(Entity $entity, ?User $pushUserOverride = null): void
    {
        $previousOverride = $this->pushUserOverride;
        $this->pushUserOverride = $pushUserOverride;

        try {
            if (!$this->integrationState->isGoogleIntegrationEnabled()) {
                return;
            }

            if (!$entity->get('saveToGoogleCalendar')) {
                return;
            }

            $actor = $this->pushUser();

            if (!$actor->getId() || $actor->isApi()) {
                return;
            }

            if (!$this->aclManager->checkEntityEdit($actor, $entity)) {
                $this->log->warning(
                    'Google Calendar sync skipped: no edit ACL for '
                    . $entity->getEntityType()
                    . ' '
                    . $entity->getId()
                    . ' user '
                    . $actor->getId()
                );

                return;
            }

            $events = $this->buildGoogleEvents($entity);

            if ($events === []) {
                $this->log->warning(
                    'Google Calendar sync skipped: no supported date fields for '
                    . $entity->getEntityType() . ' ' . $entity->getId()
                );

                return;
            }

            $client = $this->clientManager->create(Installer::INTEGRATION_ID, $actor->getId());

            if (!$client instanceof GoogleClient) {
                $this->log->warning(
                    'Google Calendar sync skipped: Google account is not connected for user ' . $actor->getId()
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

            if ($this->hasCalendarDateSources($entity->getEntityType())) {
                $this->eventRemover->removeStaleDateSourceLinks($entity, $actor, $syncedDateTypes);
            }
        } finally {
            $this->pushUserOverride = $previousOverride;
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
        $canonicalSourceDateType = $this->dateSourceProvider->canonicalSourceDateType(
            $entity->getEntityType(),
            $sourceDateType
        );

        $link = $this->findLink($entity, $sourceDateType);

        if ($link !== null && is_string($link->get('googleEventId')) && $link->get('googleEventId') !== '') {
            $oldGoogleEventId = $link->get('googleEventId');
            $oldCalendarId = is_string($link->get('calendarId')) && $link->get('calendarId') !== ''
                ? $link->get('calendarId')
                : $calendarId;

            try {
                $result = $client->updateCalendarEvent($oldGoogleEventId, $event, $calendarId);
            } catch (Error $e) {
                if ($e->getCode() !== 404) {
                    throw $e;
                }

                $this->deleteOrphanGoogleEvent($client, $oldGoogleEventId, $oldCalendarId);
                $result = $client->createCalendarEvent($event, $calendarId);
            }
        } else {
            $result = $client->createCalendarEvent($event, $calendarId);
        }

        $googleEventId = $result['id'] ?? null;

        if (!is_string($googleEventId) || $googleEventId === '') {
            throw new Error('Google Calendar sync failed: missing Google event id.');
        }

        $this->saveLink($entity, $canonicalSourceDateType, $calendarId, $googleEventId, $result['htmlLink'] ?? null);
    }

    private function findLink(Entity $entity, string $sourceDateType): ?Entity
    {
        $entityType = $entity->getEntityType();
        $canonical = $this->dateSourceProvider->canonicalSourceDateType($entityType, $sourceDateType);
        $userId = $this->pushUser()->getId();

        if ($entity->getId() === null || $entity->getId() === '' || !is_string($userId) || $userId === '') {
            return null;
        }

        $matched = null;

        foreach (
            $this->entityManager
                ->getRDBRepository(self::LINK_ENTITY_TYPE)
                ->where([
                    'sourceEntityType' => $entityType,
                    'sourceEntityId' => $entity->getId(),
                    'userId' => $userId,
                    'deleted' => false,
                ])
                ->find() as $link
        ) {
            $linkCanonical = $this->dateSourceProvider->canonicalSourceDateType(
                $entityType,
                (string) ($link->get('sourceDateType') ?? '')
            );

            if ($linkCanonical !== $canonical) {
                continue;
            }

            if ($matched !== null) {
                $this->eventRemover->removeLink($link);

                continue;
            }

            $matched = $link;
        }

        return $matched;
    }

    private function saveLink(
        Entity $entity,
        string $sourceDateType,
        string $calendarId,
        string $googleEventId,
        mixed $htmlLink
    ): void {
        $entityType = $entity->getEntityType();
        $canonical = $this->dateSourceProvider->canonicalSourceDateType($entityType, $sourceDateType);
        $userId = $this->pushUser()->getId();

        $link = $this->findLink($entity, $sourceDateType);

        if ($link === null) {
            $link = $this->findSoftDeletedLink($entity, $canonical, $userId);

            if ($link !== null) {
                $this->entityManager
                    ->getRDBRepository(self::LINK_ENTITY_TYPE)
                    ->restoreDeleted($link->getId());

                $this->entityManager->refreshEntity($link);
            }
        }

        if ($link === null) {
            $link = $this->entityManager->getNewEntity(self::LINK_ENTITY_TYPE);
            $link->set([
                'sourceEntityType' => $entityType,
                'sourceEntityId' => $entity->getId(),
                'sourceDateType' => $canonical,
                'userId' => $userId,
            ]);
        }

        $link->set([
            'name' => $entityType . ':' . $entity->getId() . ':' . $canonical . ':' . $userId,
            'sourceDateType' => $canonical,
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

    private function findSoftDeletedLink(Entity $entity, string $canonical, string $userId): ?Entity
    {
        $query = $this->entityManager->getQueryBuilder()
            ->select()
            ->from(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'sourceDateType' => $canonical,
                'userId' => $userId,
                'deleted' => true,
            ])
            ->withDeleted()
            ->build();

        return $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->clone($query)
            ->findOne();
    }

    private function deleteOrphanGoogleEvent(
        GoogleClient $client,
        string $googleEventId,
        string $calendarId
    ): void {
        try {
            $client->deleteCalendarEvent($googleEventId, $calendarId);
        } catch (Throwable $e) {
            $this->log->warning(
                'Google Calendar orphan delete skipped for event '
                . $googleEventId
                . ': '
                . $e->getMessage()
            );
        }
    }

    private function hasCalendarDateSources(string $entityType): bool
    {
        return $this->dateSourceProvider->getActiveSourcesForEntityType($entityType) !== [];
    }

    /**
     * @return array<int, array{sourceDateType: string, event: array<string, mixed>, calendarId?: string}>
     */
    private function buildGoogleEvents(Entity $entity): array
    {
        $sources = $this->dateSourceProvider->getActiveSourcesForEntityType($entity->getEntityType());

        if ($sources === []) {
            return [];
        }

        return $this->buildCalendarDateSourceGoogleEvents($entity, $sources);
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @return array<int, array{sourceDateType: string, event: array<string, mixed>, calendarId?: string}>
     */
    private function buildCalendarDateSourceGoogleEvents(Entity $entity, array $sources): array
    {
        $sourceMap = [];

        foreach ($sources as $source) {
            $sourceMap[(string) ($source['sourceDateType'] ?? self::MAIN_DATE_TYPE)] = $source;
        }

        $events = [];

        foreach ($this->getSelectedDateSourceTypes($entity, $sources) as $sourceDateType) {
            $source = $sourceMap[$sourceDateType] ?? null;
            $dateRange = $source !== null
                ? $this->buildDateRangeFromSource($entity, $source)
                : null;

            if ($dateRange === null) {
                continue;
            }

            $settings = $this->getDateSourceEventSettings($entity, $sourceDateType);

            $events[] = [
                'sourceDateType' => $sourceDateType,
                'event' => $this->buildGoogleEvent($entity, $dateRange, $sourceDateType, $settings, $source),
                'calendarId' => $this->resolveCalendarId($entity, $settings),
            ];
        }

        return $events;
    }

    /**
     * @param array<int, array<string, mixed>> $sources
     * @return array<int, string>
     */
    private function getSelectedDateSourceTypes(Entity $entity, array $sources): array
    {
        $allowed = array_values(array_filter(array_map(
            static fn (array $source): string => (string) ($source['sourceDateType'] ?? self::MAIN_DATE_TYPE),
            $sources
        )));

        $selected = $entity->get('googleCalendarDateSourceList');

        if (!is_array($selected) || $selected === []) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('strval', $selected),
            static fn (string $item): bool => in_array($item, $allowed, true)
        )));
    }

    /**
     * @return array<int, string>
     */
    private function getAllowedSourceDateTypes(string $entityType): array
    {
        return array_values(array_filter(array_map(
            static fn (array $source): string => (string) ($source['sourceDateType'] ?? self::MAIN_DATE_TYPE),
            $this->dateSourceProvider->getActiveSourcesForEntityType($entityType)
        )));
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
    private function buildGoogleEvent(
        Entity $entity,
        array $dateRange,
        string $sourceDateType,
        ?array $settings,
        ?array $source = null
    ): array {
        $event = [
            'summary' => trim((string) ($settings['summary'] ?? ''))
                ?: $this->buildSummary($entity, $sourceDateType, $source),
            'description' => $this->buildDescription($entity, $sourceDateType, $settings),
            'start' => $dateRange['start'],
            'end' => $dateRange['end'],
            'reminders' => $this->buildReminders($entity, $settings),
            'extendedProperties' => [
                'private' => [
                    'espocrmEntityType' => $entity->getEntityType(),
                    'espocrmEntityId' => (string) $entity->getId(),
                    'espocrmSourceDateType' => $sourceDateType,
                    'espocrmUserId' => (string) $this->pushUser()->getId(),
                ],
            ],
        ];

        $location = $this->buildLocation($entity, $sourceDateType, $settings);

        if ($location !== '') {
            $event['location'] = $location;
        }

        $visibility = $settings['visibility'] ?? 'default';

        if (in_array($visibility, ['default', 'private', 'public'], true)) {
            $event['visibility'] = $visibility;
        }

        $transparency = $settings['transparency'] ?? 'opaque';

        if (in_array($transparency, ['opaque', 'transparent'], true)) {
            $event['transparency'] = $transparency;
        }

        $colorId = is_string($settings['colorId'] ?? null)
            ? trim($settings['colorId'])
            : '';

        if ($colorId === '') {
            // Empty is the UI value for Google default color; the API represents it by omitting colorId.
            unset($event['colorId']);
        } else if (preg_match('/^(?:[1-9]|10|11)$/', $colorId)) {
            $event['colorId'] = $colorId;
        }

        return $event;
    }

    /**
     * @return array<string, mixed>
     */
    private function getDateSourceEventSettings(Entity $entity, string $sourceDateType): array
    {
        $rows = $entity->get('googleCalendarEventSettings');

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
            'reminderMode' => 'none',
            'reminders' => [],
            'location' => '',
            'visibility' => 'default',
            'transparency' => 'opaque',
            'colorId' => '',
            'descriptionTemplateOverride' => '',
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
     * @param array<string, mixed> $source
     * @return array{start: array<string, string>, end: array<string, string>}|null
     */
    private function buildDateRangeFromSource(Entity $entity, array $source): ?array
    {
        $dateField = (string) ($source['dateField'] ?? '');

        if ($dateField === '') {
            return null;
        }

        $startValue = $this->normalizeCalendarDateValue($entity, $dateField, $entity->get($dateField));

        if ($startValue === null) {
            return null;
        }

        if (!empty($source['allDay'])) {
            return $this->buildAllDayRange($this->toDateOnlyValue($startValue));
        }

        $endDateField = (string) ($source['endDateField'] ?? '');
        $endValue = $endDateField !== ''
            ? $this->normalizeCalendarDateValue($entity, $endDateField, $entity->get($endDateField))
            : $startValue;

        if ($endValue === null) {
            $endValue = $startValue;
        }

        return $this->buildDateTimeRange($startValue, $endValue);
    }

    private function normalizeCalendarDateValue(Entity $entity, string $fieldName, mixed $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if ($entity->getAttributeType($fieldName) === 'date') {
            return substr($value, 0, 10);
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}(?:\s+00:00:00)?$/', $value)) {
            return substr($value, 0, 10);
        }

        return $value;
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

    /**
     * CalendarDateSource all-day events need Y-m-d even when the CRM field is datetimeOptional.
     */
    private function toDateOnlyValue(?string $value): ?string
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        $value = trim($value);

        if ($this->isDateOnly($value)) {
            return $value;
        }

        if (preg_match('/^(\d{4}-\d{2}-\d{2})/', $value, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * @param ?array<string, mixed> $source
     */
    private function buildSummary(Entity $entity, string $sourceDateType, ?array $source = null): string
    {
        $name = trim((string) ($entity->get('name') ?? ''));
        $base = $name !== '' ? $name : $entity->getEntityType() . ' ' . $entity->getId();
        $label = trim((string) ($source['label'] ?? ''));

        if ($label === '') {
            $label = $this->resolveSourceLabel($entity->getEntityType(), $sourceDateType);
        }

        return $label === '' ? $base : $base . ' - ' . $label;
    }

    private function resolveSourceLabel(string $entityType, string $sourceDateType): string
    {
        foreach ($this->dateSourceProvider->getActiveSourcesForEntityType($entityType) as $source) {
            if ((string) ($source['sourceDateType'] ?? '') === $sourceDateType) {
                return trim((string) ($source['label'] ?? ''));
            }
        }

        if ($entityType === 'Opportunity') {
            return match ($sourceDateType) {
                'presentationDate' => 'Presentation date',
                'closeDate' => 'Close date',
                default => '',
            };
        }

        return '';
    }

    /**
     * @param ?array<string, mixed> $settings
     */
    private function buildDescription(Entity $entity, string $sourceDateType, ?array $settings): string
    {
        if (is_array($settings) && is_string($settings['description'] ?? null) && trim($settings['description']) !== '') {
            return trim($settings['description']);
        }

        return $this->renderTemplateString(
            $entity,
            $sourceDateType,
            $this->getDescriptionTemplate($entity, $settings)
        );
    }

    /**
     * @param ?array<string, mixed> $settings
     */
    private function buildLocation(Entity $entity, string $sourceDateType, ?array $settings): string
    {
        $location = trim((string) ($settings['location'] ?? ''));

        if ($location === '') {
            return '';
        }

        if (!str_contains($location, '{{')) {
            return $location;
        }

        return $this->renderTemplateString($entity, $sourceDateType, $location);
    }

    private function renderTemplateString(Entity $entity, string $sourceDateType, string $template): string
    {
        $template = $this->resolveRelatedTemplateVariables($entity, $template);

        return trim($this->templateRendererFactory
            ->create()
            ->setEntity($entity)
            ->setUser($this->pushUser())
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
        $mode = $settings['reminderMode'] ?? 'none';

        if (!is_string($mode) || $mode === '') {
            $mode = 'none';
        }

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

        $overrides = $this->buildCustomReminderOverrides($entity, $settings);

        return [
            'useDefault' => false,
            'overrides' => $overrides,
        ];
    }

    /**
     * @param ?array<string, mixed> $settings
     * @return array<int, array{method: string, minutes: int}>
     */
    private function buildCustomReminderOverrides(Entity $entity, ?array $settings): array
    {
        $rows = $settings['reminders'] ?? null;

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

        $field = 'googleCalendarDescriptionTemplate' . $entity->getEntityType();
        $integration = $this->entityManager->getEntityById('Integration', Installer::INTEGRATION_ID);
        $template = $integration?->get($field);

        if (is_string($template) && trim($template) !== '') {
            return $template;
        }

        $default = $this->metadata->get(['integrations', Installer::INTEGRATION_ID, 'fields', $field, 'default']);

        return is_string($default) && trim($default) !== '' ? $default : '{{name}}';
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

        unset($templateSettings['reminderMode'], $templateSettings['reminders']);

        $merged = array_merge($templateSettings, array_filter(
            $recordSettings,
            static fn (mixed $value): bool => $value !== null && $value !== ''
        ));

        $mode = $recordSettings['reminderMode'] ?? null;
        $merged['reminderMode'] = is_string($mode) && in_array($mode, ['none', 'default', 'custom'], true)
            ? $mode
            : 'none';

        return $merged;
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

    private function pushUser(): User
    {
        return $this->pushUserOverride ?? $this->sessionUser;
    }
}
