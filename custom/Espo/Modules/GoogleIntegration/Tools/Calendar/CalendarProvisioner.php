<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Log;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use RuntimeException;

/**
 * Resolves or creates per-user dedicated Google calendars for auto_dedicated routing.
 *
 * Default name is CRM - {entity scope label} so all auto_dedicated date sources for the
 * same entity share one calendar. Per-source dedicatedCalendarName still overrides.
 */
class CalendarProvisioner
{
    private const DATA_MAP_KEY = 'calendarIdMap';

    public function __construct(
        private EntityManager $entityManager,
        private ClientManager $clientManager,
        private Log $log,
        private Language $language,
    ) {}

    public function resolveDedicatedCalendarId(User $user, array $source): string
    {
        $summary = $this->resolveDedicatedCalendarName($source);
        $cacheKey = $this->buildCacheKey($source, $summary);

        $cached = $this->readCachedCalendarId($user, $cacheKey);

        if ($cached !== null) {
            return $cached;
        }

        $client = $this->createGoogleClient($user);

        $existingId = $client->findCalendarIdBySummary($summary);

        if ($existingId !== null) {
            $this->writeCachedCalendarId($user, $cacheKey, $existingId);

            return $existingId;
        }

        $created = $client->insertCalendar($summary);
        $id = trim((string) ($created['id'] ?? ''));

        if ($id === '') {
            throw new RuntimeException('Google calendar insert returned no id.');
        }

        $this->writeCachedCalendarId($user, $cacheKey, $id);

        return $id;
    }

    /**
     * @param array<string, mixed> $source
     */
    public function resolveDedicatedCalendarName(array $source): string
    {
        $name = trim((string) ($source['dedicatedCalendarName'] ?? ''));

        if ($name !== '') {
            return $name;
        }

        $entityType = trim((string) ($source['targetEntityType'] ?? ''));
        $entityLabel = $this->resolveEntityLabel($entityType);

        return 'CRM - ' . ($entityLabel !== '' ? $entityLabel : 'Calendar');
    }

    public function resolveEntityLabel(string $entityType): string
    {
        $entityType = trim($entityType);

        if ($entityType === '') {
            return '';
        }

        $label = trim((string) $this->language->translate($entityType, 'scopeNames'));

        if ($label === '' || $label === $entityType) {
            $fromEntity = trim((string) $this->language->translate($entityType, 'labels', $entityType));

            if ($fromEntity !== '' && $fromEntity !== $entityType) {
                return $fromEntity;
            }
        }

        return $label !== '' ? $label : $entityType;
    }

    /**
     * @param array<string, mixed> $source
     */
    private function buildCacheKey(array $source, string $summary): string
    {
        $entityType = (string) ($source['targetEntityType'] ?? '');

        // Same resolved name → same calendar (entity-level default shared across dates).
        return $entityType . ':' . md5(mb_strtolower($summary));
    }

    private function readCachedCalendarId(User $user, string $cacheKey): ?string
    {
        $map = $this->readCalendarIdMap($user);
        $id = trim((string) ($map[$cacheKey] ?? ''));

        return $id !== '' ? $id : null;
    }

    private function writeCachedCalendarId(User $user, string $cacheKey, string $calendarId): void
    {
        $account = $this->getExternalAccount($user);

        if ($account === null) {
            return;
        }

        $data = $account->get('data');

        if (!is_array($data)) {
            $data = [];
        }

        if (!isset($data[self::DATA_MAP_KEY]) || !is_array($data[self::DATA_MAP_KEY])) {
            $data[self::DATA_MAP_KEY] = [];
        }

        $data[self::DATA_MAP_KEY][$cacheKey] = $calendarId;
        $account->set('data', $data);

        $this->entityManager->saveEntity($account);
    }

    /**
     * @return array<string, string>
     */
    private function readCalendarIdMap(User $user): array
    {
        $account = $this->getExternalAccount($user);

        if ($account === null) {
            return [];
        }

        $data = $account->get('data');
        $map = is_array($data) ? ($data[self::DATA_MAP_KEY] ?? null) : null;

        if (!is_array($map)) {
            return [];
        }

        $normalized = [];

        foreach ($map as $key => $value) {
            if (!is_string($key)) {
                continue;
            }

            $id = trim(is_string($value) ? $value : '');

            if ($id !== '') {
                $normalized[$key] = $id;
            }
        }

        return $normalized;
    }

    private function getExternalAccount(User $user): ?Entity
    {
        $userId = $user->getId();

        if (!$userId) {
            return null;
        }

        return $this->entityManager->getEntityById(
            'ExternalAccount',
            Installer::INTEGRATION_ID . '__' . $userId
        );
    }

    private function createGoogleClient(User $user): Google
    {
        $userId = $user->getId();

        if (!$userId) {
            throw new Forbidden('No user for Google calendar provisioning.');
        }

        $client = $this->clientManager->create(Installer::INTEGRATION_ID, $userId);

        if (!$client instanceof Google) {
            throw new Forbidden('Google account is not connected.');
        }

        return $client;
    }
}
