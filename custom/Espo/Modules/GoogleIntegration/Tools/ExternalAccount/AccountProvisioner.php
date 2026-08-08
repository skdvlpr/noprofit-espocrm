<?php

namespace Espo\Modules\GoogleIntegration\Tools\ExternalAccount;

use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;
use Espo\ORM\Name\Attribute;
use PDO;

/**
 * Ensures ExternalAccount rows exist for GoogleCalendarDrive and migrates legacy ids.
 *
 * Note: Espo's ExternalAccount repository {@see \Espo\Repositories\ExternalAccount::getById}
 * fabricates an in-memory entity when the DB row is missing. Therefore
 * {@see EntityManager::getEntityById} is NOT a reliable persistence check.
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

        if (!$this->isPersisted($canonicalId)) {
            /** @var ExternalAccountEntity $entity */
            $entity = $this->entityManager->getNewEntity(ExternalAccountEntity::ENTITY_TYPE);
            $entity->set(Attribute::ID, $canonicalId);
            $entity->set('enabled', false);
            $entity->set('calendarRoutingMode', 'auto_dedicated');
            $this->copyLegacyDataIfNeeded($entity, $userId);
            $this->entityManager->saveEntity($entity);
        }

        /** @var ?ExternalAccountEntity $fresh */
        $fresh = $this->entityManager
            ->getRDBRepositoryByClass(ExternalAccountEntity::class)
            ->where([
                Attribute::ID => $canonicalId,
                Attribute::DELETED => false,
            ])
            ->findOne();

        if ($fresh === null) {
            // Fallback if soft-delete / race: fabricate via repo then force-save again.
            /** @var ExternalAccountEntity $fresh */
            $fresh = $this->entityManager->getNewEntity(ExternalAccountEntity::ENTITY_TYPE);
            $fresh->set(Attribute::ID, $canonicalId);
            $fresh->set('enabled', false);
            $fresh->set('calendarRoutingMode', 'auto_dedicated');
            $this->copyLegacyDataIfNeeded($fresh, $userId);
            $this->entityManager->saveEntity($fresh);
        }

        return $fresh;
    }

    private function isPersisted(string $id): bool
    {
        $pdo = $this->entityManager->getPDO();
        $statement = $pdo->prepare(
            'SELECT id FROM external_account WHERE id = :id AND deleted = 0 LIMIT 1'
        );
        $statement->execute(['id' => $id]);

        return $statement->fetch(PDO::FETCH_ASSOC) !== false;
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
