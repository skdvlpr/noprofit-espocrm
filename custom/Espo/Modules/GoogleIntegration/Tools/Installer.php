<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\EntityManager;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Migrates legacy Google integration rows/config/external accounts to the
 *   current id before removing the legacy ids.
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

        $this->migrateLegacyState($em, $config, $configWriter);
        $this->ensureIntegrationRow($em);
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyState(
        EntityManager $entityManager,
        Config $config,
        ConfigWriter $configWriter
    ): void {
        foreach ($this->legacyIntegrationIds() as $legacyId) {
            $this->migrateLegacyIntegrationRow($entityManager, $legacyId);
            $this->migrateLegacyExternalAccounts($entityManager, $legacyId);
        }

        $this->migrateLegacyConfig($config, $configWriter);
    }

    /**
     * @return string[]
     */
    private function legacyIntegrationIds(): array
    {
        return [
            self::LEGACY_GOOGLE_INTEGRATION_ID,
            self::LEGACY_SAFEHOUSE_GOOGLE_ID,
        ];
    }

    private function migrateLegacyIntegrationRow(EntityManager $entityManager, string $legacyId): void
    {
        /** @var ?IntegrationEntity $legacy */
        $legacy = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, $legacyId);

        if ($legacy === null) {
            return;
        }

        /** @var ?IntegrationEntity $target */
        $target = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);

        if ($target === null) {
            $data = get_object_vars($legacy->getValueMap());
            $data['id'] = self::INTEGRATION_ID;

            $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, $data, [
                SaveOption::SKIP_HOOKS => true,
            ]);
        } elseif ($this->copyMissingAttributes($target, $legacy)) {
            $entityManager->saveEntity($target, [
                SaveOption::SKIP_HOOKS => true,
            ]);
        }

        $entityManager->removeEntity($legacy, [
            SaveOption::SKIP_HOOKS => true,
        ]);
    }

    private function migrateLegacyExternalAccounts(EntityManager $entityManager, string $legacyId): void
    {
        $legacyAccountList = $entityManager
            ->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE)
            ->where(['id*' => $legacyId . '__%'])
            ->find();

        foreach ($legacyAccountList as $legacyAccount) {
            $legacyAccountId = $legacyAccount->getId();
            $targetAccountId = self::INTEGRATION_ID . substr($legacyAccountId, strlen($legacyId));

            /** @var ?ExternalAccountEntity $target */
            $target = $entityManager->getEntityById(ExternalAccountEntity::ENTITY_TYPE, $targetAccountId);

            if ($target === null) {
                $data = get_object_vars($legacyAccount->getValueMap());
                $data['id'] = $targetAccountId;

                $entityManager->createEntity(ExternalAccountEntity::ENTITY_TYPE, $data, [
                    SaveOption::SKIP_HOOKS => true,
                ]);
            } elseif ($this->copyMissingAttributes($target, $legacyAccount)) {
                $entityManager->saveEntity($target, [
                    SaveOption::SKIP_HOOKS => true,
                ]);
            }

            $entityManager->removeEntity($legacyAccount, [
                SaveOption::SKIP_HOOKS => true,
            ]);
        }
    }

    private function migrateLegacyConfig(Config $config, ConfigWriter $configWriter): void
    {
        $integrations = $config->get('integrations') ?? [];

        if (is_object($integrations)) {
            $integrationMap = get_object_vars($integrations);
        } elseif (is_array($integrations)) {
            $integrationMap = $integrations;
        } else {
            $integrationMap = [];
        }

        $changed = false;

        foreach ($this->legacyIntegrationIds() as $legacyId) {
            if (!array_key_exists($legacyId, $integrationMap)) {
                continue;
            }

            if (!array_key_exists(self::INTEGRATION_ID, $integrationMap)) {
                $integrationMap[self::INTEGRATION_ID] = (bool) $integrationMap[$legacyId];
            }

            unset($integrationMap[$legacyId]);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', (object) $integrationMap);
        $configWriter->save();
    }

    private function copyMissingAttributes(IntegrationEntity $target, IntegrationEntity $legacy): bool
    {
        $changed = false;

        foreach (get_object_vars($legacy->getValueMap()) as $attribute => $value) {
            if (!$this->shouldCopyAttribute($target, $attribute, $value)) {
                continue;
            }

            $target->set($attribute, $value);
            $changed = true;
        }

        return $changed;
    }

    private function shouldCopyAttribute(IntegrationEntity $target, string $attribute, mixed $value): bool
    {
        if ($attribute === 'id' || $value === null || $value === '') {
            return false;
        }

        if ($attribute === 'enabled') {
            return $value === true && !$target->get('enabled');
        }

        return !$target->has($attribute)
            || $target->get($attribute) === null
            || $target->get($attribute) === '';
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
