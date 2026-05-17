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
    private const MAX_REMINDERS = 5;
    private const MAX_REMINDER_MINUTES = 40320;

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private TemplateRendererFactory $templateRendererFactory,
        private Config $config,
        private Metadata $metadata,
        private User $user,
        private Log $log
    ) {}

    public function pushIfRequested(Entity $entity): void
    {
        if (!$entity->get('saveToGoogleCalendar')) {
            return;
        }

        if (!$this->user->getId() || $this->user->isApi()) {
            return;
        }

        $event = $this->buildGoogleEvent($entity);

        if ($event === null) {
            $this->log->warning(
                'Google Calendar sync skipped: no supported date fields for '
                . $entity->getEntityType() . ' ' . $entity->getId()
            );

            return;
        }

        $client = $this->clientManager->create(Installer::INTEGRATION_ID, $this->user->getId());

        if (!$client instanceof GoogleClient) {
            $this->log->warning(
                'Google Calendar sync skipped: Google account is not connected for user ' . $this->user->getId()
            );

            return;
        }

        $calendarId = self::DEFAULT_CALENDAR_ID;
        $link = $this->findLink($entity);

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

        $this->saveLink($entity, $calendarId, $googleEventId, $result['htmlLink'] ?? null);
    }

    private function findLink(Entity $entity): ?Entity
    {
        return $this->entityManager
            ->getRDBRepository(self::LINK_ENTITY_TYPE)
            ->where([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'userId' => $this->user->getId(),
                'deleted' => false,
            ])
            ->findOne();
    }

    private function saveLink(Entity $entity, string $calendarId, string $googleEventId, mixed $htmlLink): void
    {
        $link = $this->findLink($entity);

        if ($link === null) {
            $link = $this->entityManager->getNewEntity(self::LINK_ENTITY_TYPE);
            $link->set([
                'sourceEntityType' => $entity->getEntityType(),
                'sourceEntityId' => $entity->getId(),
                'userId' => $this->user->getId(),
            ]);
        }

        $link->set([
            'name' => $entity->getEntityType() . ':' . $entity->getId() . ':' . $this->user->getId(),
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
     * @return array<string, mixed>|null
     */
    private function buildGoogleEvent(Entity $entity): ?array
    {
        $dateRange = $this->buildDateRange($entity);

        if ($dateRange === null) {
            return null;
        }

        $event = [
            'summary' => $this->buildSummary($entity),
            'description' => $this->buildDescription($entity),
            'start' => $dateRange['start'],
            'end' => $dateRange['end'],
            'reminders' => $this->buildReminders($entity),
            'extendedProperties' => [
                'private' => [
                    'espocrmEntityType' => $entity->getEntityType(),
                    'espocrmEntityId' => (string) $entity->getId(),
                    'espocrmUserId' => (string) $this->user->getId(),
                ],
            ],
        ];

        $location = trim((string) ($entity->get('googleCalendarLocation') ?? ''));

        if ($location !== '') {
            $event['location'] = $location;
        }

        $visibility = $entity->get('googleCalendarVisibility');

        if (in_array($visibility, ['default', 'private', 'public'], true)) {
            $event['visibility'] = $visibility;
        }

        $transparency = $entity->get('googleCalendarTransparency');

        if (in_array($transparency, ['opaque', 'transparent'], true)) {
            $event['transparency'] = $transparency;
        }

        $colorId = $entity->get('googleCalendarColorId');

        if (is_string($colorId) && preg_match('/^(?:[1-9]|10|11)$/', $colorId)) {
            $event['colorId'] = $colorId;
        }

        return $event;
    }

    /**
     * @return array{start: array<string, string>, end: array<string, string>}|null
     */
    private function buildDateRange(Entity $entity): ?array
    {
        return match ($entity->getEntityType()) {
            'Meeting', 'Call' => $this->buildDateTimeRange($entity->get('dateStart'), $entity->get('dateEnd')),
            'Task' => $this->buildTaskDateRange($entity),
            'Opportunity' => $this->buildAllDayRange($entity->get('closeDate')),
            default => null,
        };
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

    private function buildSummary(Entity $entity): string
    {
        $name = trim((string) ($entity->get('name') ?? ''));

        if ($entity->getEntityType() === 'Opportunity') {
            return trim('Fondi e Finanziamenti: ' . ($name !== '' ? $name : $entity->getId()));
        }

        return $name !== '' ? $name : $entity->getEntityType() . ' ' . $entity->getId();
    }

    private function buildDescription(Entity $entity): string
    {
        $template = $this->getDescriptionTemplate($entity);

        return trim($this->templateRendererFactory
            ->create()
            ->setEntity($entity)
            ->setUser($this->user)
            ->setData([
                'espocrmUrl' => $this->buildRecordUrl($entity),
            ])
            ->setTemplate($template)
            ->render());
    }

    /**
     * @return array{useDefault: bool, overrides: array<int, array{method: string, minutes: int}>}
     */
    private function buildReminders(Entity $entity): array
    {
        $mode = $entity->get('googleCalendarReminderMode');

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
            'overrides' => $this->buildCustomReminderOverrides($entity),
        ];
    }

    /**
     * @return array<int, array{method: string, minutes: int}>
     */
    private function buildCustomReminderOverrides(Entity $entity): array
    {
        $rows = $entity->get('googleCalendarReminders');

        if (!is_array($rows)) {
            return [];
        }

        $overrides = [];

        foreach (array_slice($rows, 0, self::MAX_REMINDERS) as $row) {
            if (is_object($row)) {
                $row = get_object_vars($row);
            }

            if (!is_array($row)) {
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

    private function getDescriptionTemplate(Entity $entity): string
    {
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

    private function buildRecordUrl(Entity $entity): string
    {
        $siteUrl = rtrim((string) ($this->config->get('siteUrl') ?? ''), '/');

        if ($siteUrl === '') {
            return '';
        }

        return $siteUrl . '/#' . $entity->getEntityType() . '/view/' . $entity->getId();
    }
}
