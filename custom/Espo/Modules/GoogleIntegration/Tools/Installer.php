<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Migrates legacy `GoogleIntegration` / `GoogleSafehouse` rows and per-user
 *   accounts before removing obsolete ids.
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
        $configWriter = $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class);

        $this->migrateLegacyIntegrationData($em, $config, $configWriter);
        $this->removeLegacyIntegrationRow($em);
        $this->ensureIntegrationRow($em);
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationData(
        EntityManager $entityManager,
        Config $config,
        ConfigWriter $configWriter
    ): void {
        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $legacyId) {
            $this->migrateLegacyIntegrationRow($entityManager, $legacyId);
            $this->migrateLegacyExternalAccounts($entityManager, $legacyId);
        }

        $this->migrateLegacyConfigFlag($config, $configWriter);
    }

    private function migrateLegacyIntegrationRow(EntityManager $entityManager, string $legacyId): void
    {
        $repo = $entityManager->getRDBRepository(IntegrationEntity::ENTITY_TYPE);
        $legacy = $repo
            ->where(['id' => $legacyId])
            ->findOne();

        if ($legacy === null) {
            return;
        }

        $target = $repo
            ->where(['id' => self::INTEGRATION_ID])
            ->findOne();

        if ($target === null) {
            $data = get_object_vars($legacy->getValueMap());
            $data['id'] = self::INTEGRATION_ID;

            $target = $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, $data);
            $entityManager->saveEntity($target);

            return;
        }

        if ($this->copyMissingValues($target, $legacy)) {
            $entityManager->saveEntity($target);
        }
    }

    private function migrateLegacyExternalAccounts(EntityManager $entityManager, string $legacyId): void
    {
        $repo = $entityManager->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE);
        $legacyList = $repo
            ->where(['id*' => $legacyId . '__%'])
            ->find();

        foreach ($legacyList as $legacy) {
            $legacyAccountId = $legacy->getId();

            if (!is_string($legacyAccountId)) {
                continue;
            }

            $userId = substr($legacyAccountId, strlen($legacyId . '__'));
            $targetId = self::INTEGRATION_ID . '__' . $userId;
            $target = $repo
                ->where(['id' => $targetId])
                ->findOne();

            if ($target === null) {
                $data = get_object_vars($legacy->getValueMap());
                $data['id'] = $targetId;

                $target = $entityManager->createEntity(ExternalAccountEntity::ENTITY_TYPE, $data);
                $entityManager->saveEntity($target);
            } elseif ($this->copyMissingValues($target, $legacy)) {
                $entityManager->saveEntity($target);
            }

            $entityManager->removeEntity($legacy);
        }
    }

    private function migrateLegacyConfigFlag(Config $config, ConfigWriter $configWriter): void
    {
        $integrations = $config->get('integrations') ?? (object) [];

        if (is_array($integrations)) {
            $integrations = (object) $integrations;
        }

        if (!$integrations instanceof \stdClass) {
            $integrations = (object) [];
        }

        $changed = false;
        $hasTarget = property_exists($integrations, self::INTEGRATION_ID);

        foreach ([self::LEGACY_GOOGLE_INTEGRATION_ID, self::LEGACY_SAFEHOUSE_GOOGLE_ID] as $legacyId) {
            if (!property_exists($integrations, $legacyId)) {
                continue;
            }

            if (!$hasTarget) {
                $integrations->{self::INTEGRATION_ID} = (bool) $integrations->$legacyId;
                $hasTarget = true;
            }

            unset($integrations->$legacyId);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $integrations);
        $configWriter->save();
    }

    private function copyMissingValues(Entity $target, Entity $source): bool
    {
        $changed = false;

        foreach (get_object_vars($source->getValueMap()) as $name => $value) {
            if ($name === 'id') {
                continue;
            }

            $targetValue = $target->get($name);

            if ($value === null || ($targetValue !== null && $targetValue !== '')) {
                continue;
            }

            $target->set($name, $value);
            $changed = true;
        }

        return $changed;
    }

    private function removeLegacyIntegrationRow(EntityManager $entityManager): void
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

        if ($legacyList === []) {
            return;
        }

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
}
