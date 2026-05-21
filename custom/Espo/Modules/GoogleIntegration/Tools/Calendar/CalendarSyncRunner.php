<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\ApplicationUser;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\ExternalAccount\IdParser;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\Modules\GoogleIntegration\Tools\IntegrationState;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Throwable;

/**
 * Per-user background calendar sync (CRM ↔ Google) driven by ExternalAccount.calendarSyncMode.
 */
class CalendarSyncRunner
{
    private const MAX_CRM_ENTITIES_PER_USER = 100;
    private const MAX_GOOGLE_EVENTS_APPLIED_PER_USER = 100;
    /** Cap API pagination when most events on a page are not Espo-owned (wrong user / no extendedProperties). */
    private const MAX_GOOGLE_EVENTS_SCANNED_PER_USER = 500;
    private const LOOKBACK_DAYS = 30;
    private const LOOKAHEAD_DAYS = 365;

    /** @var list<string> */
    private const PULL_ENTITY_TYPES = ['Meeting', 'Call', 'Task'];

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private IntegrationState $integrationState,
        private AllowedEntityTypesProvider $allowedEntityTypesProvider,
        private EventPusher $eventPusher,
        private ApplicationUser $applicationUser,
        private Log $log
    ) {}

    public function run(): void
    {
        if (!$this->integrationState->isGoogleIntegrationEnabled()) {
            return;
        }

        $prefix = Installer::INTEGRATION_ID . '__';

        // Espo ORM: attribute* is LIKE; append % for prefix match (see AddressService, BaseQueryComposer).
        $accounts = $this->entityManager
            ->getRDBRepository('ExternalAccount')
            ->where([
                'id*' => $prefix . '%',
                'enabled' => true,
            ])
            ->find();

        foreach ($accounts as $account) {
            $id = $account->getId();

            if (!is_string($id)) {
                continue;
            }

            try {
                $parsed = IdParser::parse($id);
            } catch (Throwable) {
                continue;
            }

            $mode = $account->get('calendarSyncMode');

            if (!is_string($mode) || $mode === '' || $mode === SyncMode::NONE) {
                continue;
            }

            if (!SyncMode::isValid($mode)) {
                continue;
            }

            $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $parsed['userId']);

            if ($user === null || !$user->isActive()) {
                continue;
            }

            $client = $this->clientManager->create(Installer::INTEGRATION_ID, $user->getId());

            if (!$client instanceof GoogleClient) {
                continue;
            }

            $this->applicationUser->setUser($user);

            try {
                if (in_array($mode, [SyncMode::CRM_TO_GOOGLE, SyncMode::BIDIRECTIONAL], true)) {
                    $this->syncCrmToGoogle($user);
                }

                if (in_array($mode, [SyncMode::GOOGLE_TO_CRM, SyncMode::BIDIRECTIONAL], true)) {
                    $this->syncGoogleToCrm($user, $client);
                }
            } catch (Throwable $e) {
                $this->log->error(
                    'Google Calendar background sync failed for user '
                    . $user->getId()
                    . ': '
                    . $e->getMessage()
                );
            }
        }
    }

    private function syncCrmToGoogle(User $user): void
    {
        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->sub(new DateInterval('P' . self::LOOKBACK_DAYS . 'D'))
            ->format('Y-m-d H:i:s');

        $count = 0;

        foreach ($this->allowedEntityTypesProvider->getEntityTypeList() as $entityType) {
            if ($count >= self::MAX_CRM_ENTITIES_PER_USER) {
                break;
            }

            $collection = $this->entityManager
                ->getRDBRepository($entityType)
                ->where([
                    'saveToGoogleCalendar' => true,
                    'modifiedAt>=' => $since,
                ])
                ->limit(0, self::MAX_CRM_ENTITIES_PER_USER - $count)
                ->find();

            foreach ($collection as $entity) {
                $this->eventPusher->pushIfRequested($entity, $user);
                $count++;
            }
        }
    }

    private function syncGoogleToCrm(User $user, GoogleClient $client): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $timeMin = $now->sub(new DateInterval('P' . self::LOOKBACK_DAYS . 'D'))->format(DATE_RFC3339);
        $timeMax = $now->add(new DateInterval('P' . self::LOOKAHEAD_DAYS . 'D'))->format(DATE_RFC3339);

        $applied = 0;
        $scanned = 0;
        $pageToken = null;

        do {
            $result = $client->listCalendarEvents(
                'primary',
                $timeMin,
                $timeMax,
                250,
                $pageToken
            );

            foreach ($result['items'] as $googleEvent) {
                if (
                    $applied >= self::MAX_GOOGLE_EVENTS_APPLIED_PER_USER
                    || $scanned >= self::MAX_GOOGLE_EVENTS_SCANNED_PER_USER
                ) {
                    break 2;
                }

                $scanned++;

                if ($this->applyGoogleEventToCrm($user, $googleEvent)) {
                    $applied++;
                }
            }

            $pageToken = $result['nextPageToken'] ?? null;
        } while (
            $pageToken !== null
            && $pageToken !== ''
            && $applied < self::MAX_GOOGLE_EVENTS_APPLIED_PER_USER
            && $scanned < self::MAX_GOOGLE_EVENTS_SCANNED_PER_USER
        );
    }

    /**
     * @param array<string, mixed> $googleEvent
     */
    private function applyGoogleEventToCrm(User $user, array $googleEvent): bool
    {
        $private = $googleEvent['extendedProperties']['private'] ?? null;

        if (!is_array($private)) {
            return false;
        }

        $entityType = (string) ($private['espocrmEntityType'] ?? '');
        $entityId = (string) ($private['espocrmEntityId'] ?? '');
        $userId = (string) ($private['espocrmUserId'] ?? '');

        if (
            $entityType === ''
            || $entityId === ''
            || $userId !== $user->getId()
            || !in_array($entityType, self::PULL_ENTITY_TYPES, true)
        ) {
            return false;
        }

        $entity = $this->entityManager->getEntityById($entityType, $entityId);

        if ($entity === null || !$entity->get('saveToGoogleCalendar')) {
            return false;
        }

        $start = $this->googleEventInstantToCrm($googleEvent['start'] ?? null);
        $end = $this->googleEventInstantToCrm($googleEvent['end'] ?? null);

        if ($start === null || $end === null) {
            return false;
        }

        if ($entityType === 'Task') {
            $entity->set('dateEnd', $end);

            if ($entity->get('dateStart') === null || $entity->get('dateStart') === '') {
                $entity->set('dateStart', $start);
            }
        } else {
            $entity->set('dateStart', $start);
            $entity->set('dateEnd', $end);
        }

        $this->entityManager->saveEntity($entity);

        return true;
    }

    /**
     * @param mixed $part
     */
    private function googleEventInstantToCrm(mixed $part): ?string
    {
        if (!is_array($part)) {
            return null;
        }

        if (is_string($part['dateTime'] ?? null) && $part['dateTime'] !== '') {
            return substr(str_replace('T', ' ', $part['dateTime']), 0, 19);
        }

        if (is_string($part['date'] ?? null) && $part['date'] !== '') {
            return $part['date'] . ' 00:00:00';
        }

        return null;
    }
}
