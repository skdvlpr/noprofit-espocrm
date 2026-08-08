<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Pulls selected personal Google calendars into GoogleCalendarOverlayEvent for CRM calendar display.
 * Retention: keep future + past 30 days; delete older rows.
 */
class OverlaySyncRunner
{
    public const ENTITY_TYPE = 'GoogleCalendarOverlayEvent';

    public const RETENTION_DAYS = 30;

    /** Save/remove option: allow hooks to accept sync-written rows. */
    public const SAVE_OPTION_SYNC = 'googleOverlaySync';

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private CalendarProvisioner $calendarProvisioner,
        private Log $log,
    ) {}

    public function run(): void
    {
        $accounts = $this->entityManager
            ->getRDBRepository('ExternalAccount')
            ->where([
                'id*' => Installer::INTEGRATION_ID . '__%',
                'enabled' => true,
            ])
            ->find();

        foreach ($accounts as $account) {
            try {
                $this->syncAccount($account);
            } catch (Throwable $e) {
                $this->log->warning('Google overlay sync failed for account ' . $account->getId() . ': ' . $e->getMessage());
            }
        }
    }

    /**
     * Sync overlay events for one Espo user (on-demand from Calendar UI).
     *
     * @throws Forbidden when the account is missing, disabled, or Google client unavailable
     */
    public function syncForUser(string $userId): void
    {
        $userId = trim($userId);

        if ($userId === '') {
            throw new Forbidden('User required.');
        }

        $accountId = Installer::INTEGRATION_ID . '__' . $userId;
        $account = $this->entityManager
            ->getRDBRepository('ExternalAccount')
            ->where([
                'id' => $accountId,
                'deleted' => false,
            ])
            ->findOne();

        if ($account === null || !(bool) $account->get('enabled')) {
            throw new Forbidden('Google account is not connected or not enabled.');
        }

        $this->syncAccount($account);
    }

    public function syncAccount(Entity $account): void
    {
        $userId = $this->userIdFromAccountId((string) $account->getId());

        if ($userId === '') {
            return;
        }

        /** @var ?User $user */
        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if ($user === null) {
            return;
        }

        $client = $this->clientManager->create(Installer::INTEGRATION_ID, $userId);

        if (!$client instanceof Google) {
            return;
        }

        $calendarIds = $this->resolveOverlayCalendarIds($account, $client);

        if ($calendarIds === []) {
            $this->purgeStaleForUser($userId, []);

            return;
        }

        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $timeMin = $now->modify('-' . self::RETENTION_DAYS . ' days')->format('Y-m-d\TH:i:s\Z');
        $timeMax = $now->modify('+400 days')->format('Y-m-d\TH:i:s\Z');
        $syncedAt = $now->format('Y-m-d H:i:s');
        $seenKeys = [];

        foreach ($calendarIds as $calendarId) {
            $pageToken = null;

            do {
                $page = $client->listCalendarEvents($calendarId, $timeMin, $timeMax, 250, $pageToken);

                foreach ($page['items'] as $item) {
                    if (!is_array($item)) {
                        continue;
                    }

                    $eventId = trim((string) ($item['id'] ?? ''));

                    if ($eventId === '') {
                        continue;
                    }

                    $key = $userId . '|' . $calendarId . '|' . $eventId;
                    $seenKeys[$key] = true;
                    $this->upsertOverlayEvent($userId, $calendarId, $item, $syncedAt);
                }

                $pageToken = $page['nextPageToken'] ?? null;
            } while (is_string($pageToken) && $pageToken !== '');
        }

        $this->purgeStaleForUser($userId, $seenKeys);
    }

    /**
     * @return list<string>
     */
    private function resolveOverlayCalendarIds(Entity $account, Google $client): array
    {
        $raw = $account->get('overlayCalendarIdList');
        $selected = [];

        if (is_array($raw)) {
            foreach ($raw as $id) {
                if (is_string($id) && trim($id) !== '') {
                    $selected[] = trim($id);
                }
            }
        }

        if ($selected === []) {
            $selected = ['primary'];
        }

        $calendars = [];

        try {
            foreach ($client->listCalendars() as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = trim((string) ($item['id'] ?? ''));
                $summary = (string) ($item['summary'] ?? '');

                if ($id === '') {
                    continue;
                }

                if ($this->calendarProvisioner->isCrmCalendarName($summary)) {
                    continue;
                }

                $calendars[$id] = $summary;

                if (!empty($item['primary'])) {
                    $calendars['primary'] = $summary;
                }
            }
        } catch (Throwable $e) {
            $this->log->warning('Google overlay calendar list failed: ' . $e->getMessage());

            return array_values(array_filter($selected, static fn (string $id): bool => $id === 'primary'));
        }

        $resolved = [];

        foreach ($selected as $id) {
            if ($id === 'primary') {
                $resolved[] = 'primary';

                continue;
            }

            if (!isset($calendars[$id])) {
                continue;
            }

            if ($this->calendarProvisioner->isCrmCalendarName($calendars[$id])) {
                continue;
            }

            $resolved[] = $id;
        }

        return array_values(array_unique($resolved));
    }

    /**
     * @param array<string, mixed> $item
     */
    private function upsertOverlayEvent(string $userId, string $calendarId, array $item, string $syncedAt): void
    {
        $eventId = trim((string) ($item['id'] ?? ''));
        $title = trim((string) ($item['summary'] ?? ''));

        if ($title === '') {
            $title = '(No title)';
        }

        $existing = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'userId' => $userId,
                'googleCalendarId' => $calendarId,
                'googleEventId' => $eventId,
            ])
            ->findOne();

        $entity = $existing ?? $this->entityManager->getNewEntity(self::ENTITY_TYPE);

        $entity->set([
            'name' => mb_substr($title, 0, 255),
            'userId' => $userId,
            'googleCalendarId' => $calendarId,
            'googleEventId' => $eventId,
            'htmlLink' => is_string($item['htmlLink'] ?? null) ? $item['htmlLink'] : null,
            'lastSyncedAt' => $syncedAt,
        ]);

        $start = is_array($item['start'] ?? null) ? $item['start'] : [];
        $end = is_array($item['end'] ?? null) ? $item['end'] : [];

        if (isset($start['date']) && is_string($start['date'])) {
            $entity->set([
                'isAllDay' => true,
                'dateStartDate' => $start['date'],
                'dateEndDate' => is_string($end['date'] ?? null) ? $end['date'] : $start['date'],
                'dateStart' => null,
                'dateEnd' => null,
            ]);
        } else {
            $startDt = $this->normalizeGoogleDateTime(is_string($start['dateTime'] ?? null) ? $start['dateTime'] : null);
            $endDt = $this->normalizeGoogleDateTime(is_string($end['dateTime'] ?? null) ? $end['dateTime'] : null);

            $entity->set([
                'isAllDay' => false,
                'dateStart' => $startDt,
                'dateEnd' => $endDt ?? $startDt,
                'dateStartDate' => null,
                'dateEndDate' => null,
            ]);
        }

        $this->entityManager->saveEntity($entity, [
            self::SAVE_OPTION_SYNC => true,
        ]);
    }

    /**
     * @param array<string, bool> $seenKeys
     */
    private function purgeStaleForUser(string $userId, array $seenKeys): void
    {
        $cutoff = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->modify('-' . self::RETENTION_DAYS . ' days')
            ->format('Y-m-d H:i:s');

        $collection = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where(['userId' => $userId])
            ->find();

        foreach ($collection as $entity) {
            $calendarId = (string) $entity->get('googleCalendarId');
            $eventId = (string) $entity->get('googleEventId');
            $key = $userId . '|' . $calendarId . '|' . $eventId;

            $end = (string) ($entity->get('dateEnd') ?? $entity->get('dateEndDate') ?? $entity->get('dateStart') ?? '');

            $tooOld = false;

            if ($end !== '') {
                if (strlen($end) === 10) {
                    $tooOld = $end < substr($cutoff, 0, 10);
                } else {
                    $tooOld = $end < $cutoff;
                }
            }

            if ($tooOld || ($seenKeys !== [] && !isset($seenKeys[$key]))) {
                $this->entityManager->removeEntity($entity, [
                    self::SAVE_OPTION_SYNC => true,
                ]);
            }
        }
    }

    private function normalizeGoogleDateTime(?string $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return (new DateTimeImmutable($value))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
        } catch (Throwable) {
            return null;
        }
    }

    private function userIdFromAccountId(string $accountId): string
    {
        $prefix = Installer::INTEGRATION_ID . '__';

        if (!str_starts_with($accountId, $prefix)) {
            return '';
        }

        return substr($accountId, strlen($prefix));
    }
}
