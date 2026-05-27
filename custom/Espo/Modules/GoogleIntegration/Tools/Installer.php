<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Migrates legacy `GoogleIntegration` / `GoogleSafehouse` rows and external
 *   accounts to the canonical id before pruning stale rows.
 * - Rebuilds metadata so the integration definition is merged immediately.
 */
class Installer
{
    /** Must match {@see Resources/metadata/integrations/GoogleCalendarDrive.json} basename. */
    public const INTEGRATION_ID = 'GoogleCalendarDrive';

    /** Legacy id from an earlier SafehouseCrm-bundled draft. */
    private const LEGACY_SAFEHOUSE_GOOGLE_ID = 'GoogleSafehouse';
    /** Previous integration id before rename. */
    private const LEGACY_GOOGLE_INTEGRATION_ID = 'GoogleIntegration';

    public function runPostInstall(Container $container): void
    {
        $em = $container->getByClass(EntityManager::class);
        $this->migrateLegacyRows($em);
        $this->ensureIntegrationRow($em);
        $this->migrateConfigFlag(
            $container->getByClass(Config::class),
            $container->getByClass(ConfigWriter::class),
            $em,
        );
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyRows(EntityManager $entityManager): void
    {
        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $legacyId) {
            $this->migrateLegacyIntegrationRow($entityManager, $legacyId);
            $this->migrateLegacyExternalAccounts($entityManager, $legacyId);
        }

        $this->removeLegacyIntegrationRows($entityManager);
    }

    private function migrateLegacyIntegrationRow(EntityManager $entityManager, string $legacyId): void
    {
        /** @var ?IntegrationEntity $legacy */
        $legacy = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, $legacyId);

        if ($legacy === null) {
            return;
        }

        /** @var ?IntegrationEntity $current */
        $current = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);

        if ($current === null) {
            $entity = $entityManager->createEntity(
                IntegrationEntity::ENTITY_TYPE,
                $this->getCopiedValueMap($legacy, self::INTEGRATION_ID)
            );
            $entityManager->saveEntity($entity);

            return;
        }

        if ($this->copyMissingValues($current, $legacy)) {
            $entityManager->saveEntity($current);
        }
    }

    private function migrateLegacyExternalAccounts(EntityManager $entityManager, string $legacyId): void
    {
        $legacyPrefix = $legacyId . '__';
        $currentPrefix = self::INTEGRATION_ID . '__';

        $legacyList = $entityManager
            ->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE)
            ->where(['id*' => $legacyPrefix . '%'])
            ->find();

        foreach ($legacyList as $legacy) {
            $legacyAccountId = $legacy->getId();

            if (!is_string($legacyAccountId) || !str_starts_with($legacyAccountId, $legacyPrefix)) {
                continue;
            }

            $currentAccountId = $currentPrefix . substr($legacyAccountId, strlen($legacyPrefix));
            /** @var ?IntegrationEntity $current */
            $current = $entityManager->getEntityById(ExternalAccountEntity::ENTITY_TYPE, $currentAccountId);

            if ($current === null) {
                $entity = $entityManager->createEntity(
                    ExternalAccountEntity::ENTITY_TYPE,
                    $this->getCopiedValueMap($legacy, $currentAccountId)
                );
                $entityManager->saveEntity($entity);
            } elseif ($this->copyMissingValues($current, $legacy)) {
                $entityManager->saveEntity($current);
            }

            $entityManager->removeEntity($legacy);
        }
    }

    private function removeLegacyIntegrationRows(EntityManager $entityManager): void
    {
        $legacyList = $entityManager
            ->getRDBRepository(IntegrationEntity::ENTITY_TYPE)
            ->where([
                'OR' => [
                    ['id' => self::LEGACY_SAFEHOUSE_GOOGLE_ID],
                    ['id' => self::LEGACY_GOOGLE_INTEGRATION_ID],
                ],
            ])
            ->find();

        foreach ($legacyList as $legacy) {
            $entityManager->removeEntity($legacy);
        }
    }

    private function ensureIntegrationRow(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository(IntegrationEntity::ENTITY_TYPE);
        $existing = $repo
            ->select(['id'])
            ->where(['id' => self::INTEGRATION_ID])
            ->findOne();

        if ($existing !== null) {
            return;
        }

        $entity = $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, [
            'id' => self::INTEGRATION_ID,
            'enabled' => false,
        ]);
        $entityManager->saveEntity($entity);
    }

    /**
     * @return array<string, mixed>
     */
    private function getCopiedValueMap(IntegrationEntity $source, string $id): array
    {
        $data = get_object_vars($source->getValueMap());
        $data['id'] = $id;

        return $data;
    }

    private function copyMissingValues(IntegrationEntity $target, IntegrationEntity $source): bool
    {
        $changed = false;

        foreach (get_object_vars($source->getValueMap()) as $name => $value) {
            if ($name === 'id' || !$this->hasValue($value) || $this->hasValue($target->get($name))) {
                continue;
            }

            $target->set($name, $value);
            $changed = true;
        }

        if (
            !$target->get('enabled')
            && $source->get('enabled')
            && ($this->hasOAuthCredentials($source) || $this->hasExternalAccountToken($source))
        ) {
            $target->set('enabled', true);
            $changed = true;
        }

        return $changed;
    }

    private function hasValue(mixed $value): bool
    {
        return $value !== null && $value !== '';
    }

    private function hasOAuthCredentials(IntegrationEntity $entity): bool
    {
        return $this->hasValue($entity->get('clientId')) && $this->hasValue($entity->get('clientSecret'));
    }

    private function hasExternalAccountToken(IntegrationEntity $entity): bool
    {
        return $this->hasValue($entity->get('accessToken')) || $this->hasValue($entity->get('refreshToken'));
    }

    private function migrateConfigFlag(Config $config, ConfigWriter $configWriter, EntityManager $entityManager): void
    {
        $integrations = $config->get('integrations') ?? (object) [];

        if (is_array($integrations)) {
            $integrations = (object) $integrations;
        }

        if (!$integrations instanceof stdClass) {
            $integrations = (object) [];
        }

        $changed = false;
        $legacyIds = [self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID];

        foreach ($legacyIds as $legacyId) {
            if (!property_exists($integrations, $legacyId)) {
                continue;
            }

            if (!property_exists($integrations, self::INTEGRATION_ID)) {
                $integrations->{self::INTEGRATION_ID} = (bool) $integrations->$legacyId;
            }

            unset($integrations->$legacyId);
            $changed = true;
        }

        if (!property_exists($integrations, self::INTEGRATION_ID)) {
            $integration = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);

            if ($integration !== null && $integration->get('enabled')) {
                $integrations->{self::INTEGRATION_ID} = true;
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $integrations);
        $configWriter->save();
    }
}
