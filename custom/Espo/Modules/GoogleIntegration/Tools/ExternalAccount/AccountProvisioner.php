<?php

namespace Espo\Modules\GoogleIntegration\Tools\ExternalAccount;

use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use PDO;
use PDOException;
use Throwable;

/**
 * Ensures ExternalAccount rows exist for GoogleCalendarDrive and migrates legacy ids.
 *
 * Note: Espo's ExternalAccount repository {@see \Espo\Repositories\ExternalAccount::getById}
 * fabricates an in-memory entity when the DB row is missing. Therefore
 * {@see EntityManager::getEntityById} is NOT a reliable persistence check.
 *
 * Soft-deleted rows still occupy the primary key — must restore, never re-INSERT.
 * Parallel GET read + getOAuth2Info both call ensureForUser; insert must be race-safe.
 */
class AccountProvisioner
{
    /** @var list<string> */
    private const LEGACY_INTEGRATION_IDS = [
        'GoogleIntegration',
        'GoogleSafehouse',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function ensureForUser(string $userId): ExternalAccountEntity
    {
        $canonicalId = Installer::INTEGRATION_ID . '__' . $userId;

        $existing = $this->fetchRowAnyDeleted($canonicalId);

        if ($existing !== null) {
            if ((int) ($existing['deleted'] ?? 0) === 1) {
                $this->restoreSoftDeleted($canonicalId, $userId);
            }

            return $this->requirePersistedEntity($canonicalId);
        }

        try {
            /** @var ExternalAccountEntity $entity */
            $entity = $this->entityManager->getNewEntity(ExternalAccountEntity::ENTITY_TYPE);
            $entity->set(Attribute::ID, $canonicalId);
            $entity->set('enabled', false);
            $entity->set('calendarRoutingMode', 'auto_dedicated');
            $entity->set('allowManagerGoogleCalendarWrite', false);
            $this->copyLegacyDataIfNeeded($entity, $userId);
            $this->entityManager->saveEntity($entity);
        } catch (Throwable $e) {
            if (!$this->isDuplicateKey($e)) {
                throw $e;
            }
            // Parallel provision won the insert — fall through to load.
        }

        return $this->requirePersistedEntity($canonicalId);
    }

    /**
     * @return ?array{id: string, deleted: int|string}
     */
    private function fetchRowAnyDeleted(string $id): ?array
    {
        $pdo = $this->entityManager->getPDO();
        $statement = $pdo->prepare(
            'SELECT id, deleted FROM external_account WHERE id = :id LIMIT 1'
        );
        $statement->execute(['id' => $id]);
        $row = $statement->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    }

    private function restoreSoftDeleted(string $canonicalId, string $userId): void
    {
        $pdo = $this->entityManager->getPDO();
        $statement = $pdo->prepare(
            'UPDATE external_account SET deleted = 0 WHERE id = :id AND deleted = 1'
        );
        $statement->execute(['id' => $canonicalId]);

        /** @var ?ExternalAccountEntity $entity */
        $entity = $this->entityManager
            ->getRDBRepositoryByClass(ExternalAccountEntity::class)
            ->where([
                Attribute::ID => $canonicalId,
                Attribute::DELETED => false,
            ])
            ->findOne();

        if ($entity === null) {
            return;
        }

        // Only fill missing defaults; do not wipe tokens if any survived soft-delete.
        if ($entity->get('calendarRoutingMode') === null || $entity->get('calendarRoutingMode') === '') {
            $entity->set('calendarRoutingMode', 'auto_dedicated');
        }

        if ($entity->get('allowManagerGoogleCalendarWrite') === null) {
            $entity->set('allowManagerGoogleCalendarWrite', false);
        }

        $this->copyLegacyDataIfNeeded($entity, $userId);
        $this->entityManager->saveEntity($entity);
    }

    private function requirePersistedEntity(string $canonicalId): ExternalAccountEntity
    {
        /** @var ?ExternalAccountEntity $fresh */
        $fresh = $this->entityManager
            ->getRDBRepositoryByClass(ExternalAccountEntity::class)
            ->where([
                Attribute::ID => $canonicalId,
                Attribute::DELETED => false,
            ])
            ->findOne();

        if ($fresh === null) {
            throw new \RuntimeException(
                'Failed to provision ExternalAccount ' . $canonicalId
            );
        }

        return $fresh;
    }

    private function isDuplicateKey(Throwable $e): bool
    {
        if ($e instanceof PDOException && (string) $e->getCode() === '23000') {
            return true;
        }

        $previous = $e->getPrevious();

        if ($previous instanceof PDOException && (string) $previous->getCode() === '23000') {
            return true;
        }

        $message = $e->getMessage() . ($previous ? $previous->getMessage() : '');

        return str_contains($message, 'Duplicate entry')
            || str_contains($message, '23000');
    }

    private function copyLegacyDataIfNeeded(ExternalAccountEntity $canonical, string $userId): void
    {
        foreach (self::LEGACY_INTEGRATION_IDS as $legacyIntegration) {
            $legacyId = $legacyIntegration . '__' . $userId;

            $legacy = $this->entityManager
                ->getRDBRepositoryByClass(ExternalAccountEntity::class)
                ->where([
                    Attribute::ID => $legacyId,
                    Attribute::DELETED => false,
                ])
                ->findOne();

            if ($legacy === null) {
                continue;
            }

            $data = $legacy->get('data');

            if (is_array($data) || is_object($data)) {
                $canonical->set('data', $data);
            }

            foreach (['accessToken', 'refreshToken', 'tokenType', 'expiresAt'] as $tokenField) {
                $value = $legacy->get($tokenField);

                if ($value !== null && $value !== '') {
                    $canonical->set($tokenField, $value);
                }
            }

            $canonical->set('enabled', (bool) $legacy->get('enabled'));

            return;
        }
    }
}
