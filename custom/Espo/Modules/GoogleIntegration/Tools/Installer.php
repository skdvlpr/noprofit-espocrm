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
 * - Migrates legacy integration rows so saved credentials and user OAuth tokens
 *   survive id renames / split-out upgrades.
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

    /** @var string[] */
    private const LEGACY_INTEGRATION_IDS = [
        self::LEGACY_GOOGLE_INTEGRATION_ID,
        self::LEGACY_SAFEHOUSE_GOOGLE_ID,
    ];

    /** @var string[] Data keys that are not proof of a connected OAuth account. */
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
        $this->migrateLegacyExternalAccountRows($em);
        $this->migrateLegacyConfig($config, $configWriter, $em);
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationRows(EntityManager $entityManager): void
    {
        $target = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);

        foreach (self::LEGACY_INTEGRATION_IDS as $legacyId) {
            $legacy = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, $legacyId);

            if ($legacy === null) {
                continue;
            }

            if ($target === null) {
                $target = $entityManager->getNewEntity(IntegrationEntity::ENTITY_TYPE);
                $target->set('id', self::INTEGRATION_ID);
                $target->set('enabled', false);
            }

            $this->copyMissingValues($legacy, $target);
            $entityManager->saveEntity($target);
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

    private function migrateLegacyExternalAccountRows(EntityManager $entityManager): void
    {
        foreach (self::LEGACY_INTEGRATION_IDS as $legacyId) {
            $legacyList = $entityManager
                ->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE)
                ->where(['id*' => $legacyId . '__%'])
                ->find();

            foreach ($legacyList as $legacy) {
                $legacyIdValue = $legacy->getId();

                if (!is_string($legacyIdValue)) {
                    continue;
                }

                $userId = substr($legacyIdValue, strlen($legacyId . '__'));

                if ($userId === '') {
                    continue;
                }

                $targetId = self::INTEGRATION_ID . '__' . $userId;
                $target = $entityManager->getEntityById(ExternalAccountEntity::ENTITY_TYPE, $targetId);

                if ($target === null) {
                    $target = $entityManager->getNewEntity(ExternalAccountEntity::ENTITY_TYPE);
                    $target->set('id', $targetId);
                    $target->set('enabled', false);
                }

                $this->copyMissingValues($legacy, $target);
                $entityManager->saveEntity($target);
                $entityManager->removeEntity($legacy);
            }
        }
    }

    private function migrateLegacyConfig(
        Config $config,
        ConfigWriter $configWriter,
        EntityManager $entityManager
    ): void
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

        $target = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);
        $targetEnabled = $target !== null ? (bool) $target->get('enabled') : null;
        $enabled = $legacyEnabled ?? $targetEnabled;

        if (!property_exists($integrations, self::INTEGRATION_ID) && $enabled !== null) {
            $integrations->{self::INTEGRATION_ID} = $enabled;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $integrations);
        $configWriter->save();
    }

    private function copyMissingValues(IntegrationEntity $source, IntegrationEntity $target): void
    {
        $targetHasCredentials = $this->hasCredentialPayload($target);

        foreach (get_object_vars($source->getValueMap()) as $name => $value) {
            if ($name === 'id' || $value === null) {
                continue;
            }

            if ($name === 'enabled') {
                if (!$targetHasCredentials) {
                    $target->set($name, (bool) $value);
                }

                continue;
            }

            if ($target->has($name) && $target->get($name) !== null && $target->get($name) !== '') {
                continue;
            }

            $target->set($name, $value);
        }
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
