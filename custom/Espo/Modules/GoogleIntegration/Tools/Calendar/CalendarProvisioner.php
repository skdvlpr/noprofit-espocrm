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
    /** Legacy hardcoded prefix, kept for CRM-calendar detection on pre-existing calendars. */
    private const LEGACY_PREFIX = 'CRM';
    private const DEFAULT_PREFIX = 'CRM';

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

        return $this->buildCalendarName($entityLabel !== '' ? $entityLabel : 'Calendar');
    }

    /**
     * Formats `{prefix}-{label}-{suffix}` using the admin-configured
     * Integrations → Google → prefix/suffix, stripping dangling separators when
     * prefix and/or suffix are empty.
     */
    public function buildCalendarName(string $label): string
    {
        [$prefix, $suffix] = $this->getPrefixSuffix();

        $parts = array_values(array_filter(
            [$prefix, trim($label), $suffix],
            static fn (string $part): bool => $part !== ''
        ));

        return implode('-', $parts);
    }

    /**
     * Detects Google calendars that were created/named by this CRM (current prefix/suffix,
     * or the legacy hardcoded "CRM - " / "CRM-" prefixes). Used to exclude CRM
     * calendars from the personal overlay picker so CRM ↔ Google display never loops.
     */
    public function isCrmCalendarName(string $name): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        if (str_starts_with($name, self::LEGACY_PREFIX . ' - ') ||
            str_starts_with($name, self::LEGACY_PREFIX . '-')
        ) {
            return true;
        }

        [$prefix, $suffix] = $this->getPrefixSuffix();

        if ($prefix === '' && $suffix === '') {
            return false;
        }

        if ($prefix !== '') {
            $withDash = $prefix . '-';
            $withSpaced = $prefix . ' - ';

            if (!str_starts_with($name, $withDash) && !str_starts_with($name, $withSpaced)) {
                return false;
            }
        }

        if ($suffix !== '') {
            $withDash = '-' . $suffix;
            $withSpaced = ' - ' . $suffix;

            if (!str_ends_with($name, $withDash) && !str_ends_with($name, $withSpaced)) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{0: string, 1: string} [prefix, suffix]
     */
    public function getPrefixSuffix(): array
    {
        $integration = $this->entityManager->getEntityById('Integration', Installer::INTEGRATION_ID);

        $rawPrefix = $integration?->get('googleCalendarNamePrefix');
        $rawSuffix = $integration?->get('googleCalendarNameSuffix');

        // Integration rows created before C shipped have no stored value — fall back to the
        // metadata default instead of silently going empty.
        $prefix = $rawPrefix === null ? self::DEFAULT_PREFIX : trim((string) $rawPrefix);
        $suffix = is_string($rawSuffix) ? trim($rawSuffix) : '';

        return [$prefix, $suffix];
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
