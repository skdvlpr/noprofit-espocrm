<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\ExternalAccount;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Migrates legacy integration ids before cleanup so upgrades do not drop
 *   saved admin OAuth credentials or per-user tokens.
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
        $config = $container->getByClass(Config::class);
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $configChanged = $this->migrateLegacyIntegrationRows($em, $config, $configWriter);
        $this->migrateLegacyExternalAccountRows($em);
        $this->ensureIntegrationRow($em);

        if ($configChanged) {
            $configWriter->save();
            $config->update();
        }

        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationRows(
        EntityManager $entityManager,
        Config $config,
        ConfigWriter $configWriter
    ): bool {
        $source = $this->findLegacyIntegrationSource($entityManager);
        $repo = $entityManager->getRDBRepository(IntegrationEntity::ENTITY_TYPE);
        $canonical = $repo
            ->where(['id' => self::INTEGRATION_ID])
            ->findOne();

        $canonicalConfigured = $canonical !== null && $this->isConfigured($canonical);

        if ($source !== null && !$canonicalConfigured) {
            $canonical ??= $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, [
                'id' => self::INTEGRATION_ID,
            ]);

            $this->copyIntegrationData($source, $canonical);
            $entityManager->saveEntity($canonical);
        }

        $this->removeLegacyIntegrationRows($entityManager);

        return $this->migrateIntegrationConfig($config, $configWriter, $source, $canonicalConfigured);
    }

    private function findLegacyIntegrationSource(EntityManager $entityManager): ?IntegrationEntity
    {
        $repo = $entityManager->getRDBRepository(IntegrationEntity::ENTITY_TYPE);

        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $id) {
            $entity = $repo
                ->where(['id' => $id])
                ->findOne();

            if ($entity !== null) {
                /** @var IntegrationEntity $entity */
                return $entity;
            }
        }

        return null;
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

    private function copyIntegrationData(IntegrationEntity $source, IntegrationEntity $target): void
    {
        $data = get_object_vars($source->getValueMap());
        unset($data['id']);

        $target->set($data);
    }

    private function migrateIntegrationConfig(
        Config $config,
        ConfigWriter $configWriter,
        ?IntegrationEntity $source,
        bool $canonicalConfigured
    ): bool {
        $integrations = $config->get('integrations') ?? (object) [];
        $configData = is_object($integrations) ? clone $integrations : (object) [];
        $before = json_encode($configData);

        $hadCanonical = property_exists($configData, self::INTEGRATION_ID);
        $legacyValue = null;

        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $legacyId) {
            if (property_exists($configData, $legacyId)) {
                $legacyValue ??= $configData->$legacyId;
                unset($configData->$legacyId);
            }
        }

        if ($source !== null && (!$hadCanonical || !$canonicalConfigured)) {
            $configData->{self::INTEGRATION_ID} = $legacyValue ?? (bool) $source->get('enabled');
        }

        if (json_encode($configData) === $before) {
            return false;
        }

        $configWriter->set('integrations', $configData);

        return true;
    }

    private function migrateLegacyExternalAccountRows(EntityManager $entityManager): void
    {
        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $legacyId) {
            $this->migrateLegacyExternalAccountPrefix($entityManager, $legacyId);
        }
    }

    private function migrateLegacyExternalAccountPrefix(EntityManager $entityManager, string $legacyId): void
    {
        $repo = $entityManager->getRDBRepository(ExternalAccount::ENTITY_TYPE);
        $legacyList = $repo
            ->where(['id*' => $legacyId . '__%'])
            ->find();

        foreach ($legacyList as $legacy) {
            $oldId = $legacy->getId();

            if (!is_string($oldId) || !str_starts_with($oldId, $legacyId . '__')) {
                continue;
            }

            $newId = self::INTEGRATION_ID . substr($oldId, strlen($legacyId));
            $canonical = $entityManager->getEntityById(ExternalAccount::ENTITY_TYPE, $newId);

            if ($canonical === null || !$this->hasExternalAccountTokens($canonical)) {
                $canonical ??= $entityManager->createEntity(ExternalAccount::ENTITY_TYPE, [
                    'id' => $newId,
                ]);

                $this->copyIntegrationLikeData($legacy, $canonical);
                $entityManager->saveEntity($canonical, [SaveOption::SKIP_ALL => true]);
            }

            $entityManager->removeEntity($legacy, [SaveOption::SKIP_ALL => true]);
        }
    }

    private function copyIntegrationLikeData(Entity $source, Entity $target): void
    {
        $data = get_object_vars($source->getValueMap());
        unset($data['id']);

        $target->set($data);
    }

    private function isConfigured(Entity $entity): bool
    {
        $clientId = $entity->get('clientId');
        $clientSecret = $entity->get('clientSecret');

        return is_string($clientId) && $clientId !== ''
            && is_string($clientSecret) && $clientSecret !== '';
    }

    private function hasExternalAccountTokens(Entity $entity): bool
    {
        foreach (['accessToken', 'refreshToken'] as $field) {
            $value = $entity->get($field);

            if (is_string($value) && $value !== '') {
                return true;
            }
        }

        return false;
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
}
