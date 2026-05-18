<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Migrates legacy integration rows so saved admin credentials survive
 *   renames/split-out upgrades.
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
        self::LEGACY_SAFEHOUSE_GOOGLE_ID,
        self::LEGACY_GOOGLE_INTEGRATION_ID,
    ];

    public function runPostInstall(Container $container): void
    {
        $em = $container->getByClass(EntityManager::class);
        $this->migrateLegacyIntegrationRows($em);
        $this->migrateLegacyExternalAccountRows($em);
        $this->ensureIntegrationRow($em);
        $this->syncIntegrationConfig(
            $container->getByClass(Config::class),
            $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class),
            $em,
        );
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationRows(EntityManager $entityManager): void
    {
        $legacyList = $this->getLegacyIntegrationRows($entityManager);
        if ($legacyList === []) {
            return;
        }

        $target = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);
        $overwriteExistingValues = false;

        if ($target === null) {
            $target = $entityManager->getNewEntity(IntegrationEntity::ENTITY_TYPE);
            $target->set('id', self::INTEGRATION_ID);
            $target->set('enabled', false);
            $overwriteExistingValues = true;
        }

        foreach ($legacyList as $legacy) {
            $this->copyIntegrationValues($legacy, $target, $overwriteExistingValues);
        }

        $entityManager->saveEntity($target);

        foreach ($legacyList as $legacy) {
            $entityManager->removeEntity($legacy);
        }
    }

    /**
     * @return IntegrationEntity[]
     */
    private function getLegacyIntegrationRows(EntityManager $entityManager): array
    {
        $legacyList = [];

        foreach (self::LEGACY_INTEGRATION_IDS as $id) {
            $legacy = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, $id);

            if ($legacy !== null) {
                $legacyList[] = $legacy;
            }
        }

        return $legacyList;
    }

    private function migrateLegacyExternalAccountRows(EntityManager $entityManager): void
    {
        foreach (self::LEGACY_INTEGRATION_IDS as $legacyId) {
            $legacyAccountList = $entityManager
                ->getRDBRepository('ExternalAccount')
                ->where(['id*' => $legacyId . '__%'])
                ->find();

            foreach ($legacyAccountList as $legacyAccount) {
                $legacyAccountId = $legacyAccount->getId();

                if (!is_string($legacyAccountId)) {
                    continue;
                }

                $userId = substr($legacyAccountId, strlen($legacyId . '__'));
                if ($userId === '') {
                    continue;
                }

                $targetAccountId = self::INTEGRATION_ID . '__' . $userId;
                $targetAccount = $entityManager->getEntityById('ExternalAccount', $targetAccountId);
                $overwriteExistingValues = false;

                if ($targetAccount === null) {
                    $targetAccount = $entityManager->getNewEntity('ExternalAccount');
                    $targetAccount->set('id', $targetAccountId);
                    $targetAccount->set('enabled', false);
                    $overwriteExistingValues = true;
                }

                $this->copyIntegrationValues($legacyAccount, $targetAccount, $overwriteExistingValues);
                $entityManager->saveEntity($targetAccount);
                $entityManager->removeEntity($legacyAccount);
            }
        }
    }

    private function copyIntegrationValues(
        IntegrationEntity $source,
        IntegrationEntity $target,
        bool $overwriteExistingValues
    ): void
    {
        foreach (get_object_vars($source->getValueMap()) as $name => $value) {
            if ($name === 'id' || $value === null) {
                continue;
            }

            if (
                !$overwriteExistingValues
                && $target->has($name)
                && $target->get($name) !== null
                && $target->get($name) !== ''
            ) {
                continue;
            }

            $target->set($name, $value);
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

    private function syncIntegrationConfig(
        Config $config,
        ConfigWriter $configWriter,
        EntityManager $entityManager
    ): void {
        $integration = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);
        $enabled = $integration !== null && (bool) $integration->get('enabled');
        $integrations = $config->get('integrations') ?? (object) [];

        if (is_array($integrations)) {
            $integrations = (object) $integrations;
        }

        if (!$integrations instanceof stdClass) {
            $integrations = (object) [];
        }

        $changed = false;

        foreach (self::LEGACY_INTEGRATION_IDS as $legacyId) {
            if (property_exists($integrations, $legacyId)) {
                unset($integrations->$legacyId);
                $changed = true;
            }
        }

        if (($integrations->{self::INTEGRATION_ID} ?? null) !== $enabled) {
            $integrations->{self::INTEGRATION_ID} = $enabled;
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $integrations);
        $configWriter->save();
    }
}
