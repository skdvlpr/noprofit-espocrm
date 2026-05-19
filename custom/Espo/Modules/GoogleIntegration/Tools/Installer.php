<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
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
 * - Migrates legacy `GoogleIntegration` / `GoogleSafehouse` rows if present
 *   before removing them.
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

    private const LEGACY_INTEGRATION_IDS = [
        self::LEGACY_GOOGLE_INTEGRATION_ID,
        self::LEGACY_SAFEHOUSE_GOOGLE_ID,
    ];

    private const NON_CREDENTIAL_DATA_KEYS = ['calendarSyncMode'];

    public function runPostInstall(Container $container): void
    {
        $em = $container->getByClass(EntityManager::class);
        $config = $container->getByClass(Config::class);
        $configWriter = $container
            ->getByClass(InjectableFactory::class)
            ->create(ConfigWriter::class);

        $this->migrateLegacyIntegrationRows($em);
        $this->ensureIntegrationRow($em);
        $this->migrateLegacyExternalAccounts($em);
        $this->migrateLegacyConfig($config, $configWriter);
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationRows(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository(IntegrationEntity::ENTITY_TYPE);
        $target = $repo
            ->where(['id' => self::INTEGRATION_ID])
            ->findOne();

        foreach (self::LEGACY_INTEGRATION_IDS as $legacyId) {
            $legacy = $repo
                ->where(['id' => $legacyId])
                ->findOne();

            if ($legacy === null) {
                continue;
            }

            if ($target === null) {
                $target = $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, [
                    'id' => self::INTEGRATION_ID,
                ]);
            }

            $this->mergeIntegrationPayload($target, $legacy);
            $entityManager->saveEntity($target);
            $entityManager->removeEntity($legacy);
        }
    }

    private function migrateLegacyExternalAccounts(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE);

        foreach (self::LEGACY_INTEGRATION_IDS as $legacyId) {
            $legacyList = $repo
                ->where(['id*' => $legacyId . '__%'])
                ->find();

            foreach ($legacyList as $legacy) {
                $legacyExternalAccountId = $legacy->getId();

                if (!is_string($legacyExternalAccountId)) {
                    continue;
                }

                $userId = substr($legacyExternalAccountId, strlen($legacyId . '__'));
                $targetId = self::INTEGRATION_ID . '__' . $userId;
                $target = $repo
                    ->where(['id' => $targetId])
                    ->findOne();

                if ($target === null) {
                    $target = $entityManager->createEntity(ExternalAccountEntity::ENTITY_TYPE, [
                        'id' => $targetId,
                    ]);
                }

                $this->mergeIntegrationPayload($target, $legacy);
                $this->copyAttributeIfMissing($target, $legacy, 'isLocked');
                $entityManager->saveEntity($target);
                $entityManager->removeEntity($legacy);
            }
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

    private function migrateLegacyConfig(Config $config, ConfigWriter $configWriter): void
    {
        $integrations = $this->normalizeIntegrationsConfig($config->get('integrations'));
        $legacyEnabled = null;
        $changed = false;

        foreach (self::LEGACY_INTEGRATION_IDS as $legacyId) {
            if (!property_exists($integrations, $legacyId)) {
                continue;
            }

            if ($legacyEnabled === null) {
                $legacyEnabled = (bool) $integrations->$legacyId;
            }

            unset($integrations->$legacyId);
            $changed = true;
        }

        if (!property_exists($integrations, self::INTEGRATION_ID) && $legacyEnabled !== null) {
            $integrations->{self::INTEGRATION_ID} = $legacyEnabled;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $integrations);
        $configWriter->save();
    }

    private function mergeIntegrationPayload(IntegrationEntity $target, IntegrationEntity $source): void
    {
        if (!$this->hasCredentialPayload($target)) {
            $target->set('enabled', (bool) $source->get('enabled'));
        }

        $targetData = $this->cloneData($target);
        $sourceData = $source->getData();

        foreach (get_object_vars($sourceData) as $name => $value) {
            if (
                property_exists($targetData, $name)
                && $targetData->$name !== null
                && $targetData->$name !== ''
            ) {
                continue;
            }

            $targetData->$name = $value;
        }

        $target->set('data', $targetData);
    }

    private function copyAttributeIfMissing(IntegrationEntity $target, IntegrationEntity $source, string $attribute): void
    {
        if (!$source->has($attribute)) {
            return;
        }

        if ($target->has($attribute) && $target->get($attribute) !== null) {
            return;
        }

        $target->set($attribute, $source->get($attribute));
    }

    private function hasCredentialPayload(IntegrationEntity $entity): bool
    {
        foreach (get_object_vars($entity->getData()) as $name => $value) {
            if (in_array($name, self::NON_CREDENTIAL_DATA_KEYS, true)) {
                continue;
            }

            if ($value !== null && $value !== '') {
                return true;
            }
        }

        return false;
    }

    private function cloneData(IntegrationEntity $entity): stdClass
    {
        return (object) get_object_vars($entity->getData());
    }

    private function normalizeIntegrationsConfig(mixed $integrations): stdClass
    {
        if ($integrations instanceof stdClass) {
            return (object) get_object_vars($integrations);
        }

        if (is_array($integrations)) {
            return (object) $integrations;
        }

        return (object) [];
    }
}
