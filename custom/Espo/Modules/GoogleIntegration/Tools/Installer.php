<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Entities\ExternalAccount as ExternalAccountEntity;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use stdClass;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Migrates legacy integration ids (`GoogleIntegration`, `GoogleSafehouse`)
 *   to {@see self::INTEGRATION_ID} without dropping OAuth credentials or
 *   per-user tokens.
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
        $this->migrateLegacyIntegrationRows($em);
        $this->ensureIntegrationRow($em);
        $this->migrateLegacyExternalAccounts($em);
        $this->migrateLegacyConfig(
            $container->getByClass(Config::class),
            $container->getByClass(ConfigWriter::class),
            $em,
        );
        $this->removeLegacyIntegrationRows($em);
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationRows(EntityManager $entityManager): void
    {
        $current = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);
        $legacyList = $this->findLegacyIntegrationRows($entityManager);

        foreach ($legacyList as $legacy) {
            if ($current === null) {
                $current = $this->cloneEntity($entityManager, IntegrationEntity::ENTITY_TYPE, $legacy, self::INTEGRATION_ID);

                continue;
            }

            $this->copyMissingValues($current, $legacy);
            $entityManager->saveEntity($current);
        }
    }

    private function removeLegacyIntegrationRows(EntityManager $entityManager): void
    {
        foreach ($this->findLegacyIntegrationRows($entityManager) as $legacy) {
            $entityManager->removeEntity($legacy);
        }
    }

    /**
     * @return iterable<Entity>
     */
    private function findLegacyIntegrationRows(EntityManager $entityManager): iterable
    {
        return $entityManager
            ->getRDBRepository(IntegrationEntity::ENTITY_TYPE)
            ->where([
                'OR' => [
                    ['id' => self::LEGACY_SAFEHOUSE_GOOGLE_ID],
                    ['id' => self::LEGACY_GOOGLE_INTEGRATION_ID],
                ],
            ])
            ->find();
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

        $entityManager->createEntity(IntegrationEntity::ENTITY_TYPE, [
            'id' => self::INTEGRATION_ID,
            'enabled' => false,
        ]);
    }

    private function migrateLegacyExternalAccounts(EntityManager $entityManager): void
    {
        foreach ($this->getLegacyIntegrationIdList() as $legacyId) {
            $legacyAccountList = $entityManager
                ->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE)
                ->where(['id*' => $legacyId . '__%'])
                ->find();

            foreach ($legacyAccountList as $legacyAccount) {
                $legacyAccountId = $legacyAccount->getId();

                if (!is_string($legacyAccountId)) {
                    continue;
                }

                $targetId = self::INTEGRATION_ID . substr($legacyAccountId, strlen($legacyId));
                $target = $entityManager->getEntityById(ExternalAccountEntity::ENTITY_TYPE, $targetId);

                if ($target === null) {
                    $this->cloneEntity($entityManager, ExternalAccountEntity::ENTITY_TYPE, $legacyAccount, $targetId);
                } else {
                    $this->copyMissingValues($target, $legacyAccount);
                    $entityManager->saveEntity($target);
                }

                $entityManager->removeEntity($legacyAccount);
            }
        }
    }

    private function migrateLegacyConfig(
        Config $config,
        ConfigWriter $configWriter,
        EntityManager $entityManager,
    ): void {
        $rawIntegrations = $config->get('integrations');
        $integrations = $this->normalizeConfigIntegrations($rawIntegrations);
        $changed = is_array($rawIntegrations) || $rawIntegrations === null;

        $legacyConfigValue = null;
        foreach ($this->getLegacyIntegrationIdList() as $legacyId) {
            if (property_exists($integrations, $legacyId)) {
                $legacyConfigValue = (bool) $legacyConfigValue || (bool) $integrations->{$legacyId};
            }
        }

        if (
            $legacyConfigValue === true &&
            (!property_exists($integrations, self::INTEGRATION_ID) || $integrations->{self::INTEGRATION_ID} !== true)
        ) {
            $integrations->{self::INTEGRATION_ID} = true;
            $changed = true;
        } elseif (!property_exists($integrations, self::INTEGRATION_ID) && $legacyConfigValue !== null) {
            $integrations->{self::INTEGRATION_ID} = $legacyConfigValue;
            $changed = true;
        }

        if (!property_exists($integrations, self::INTEGRATION_ID)) {
            $current = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);

            if ($current !== null) {
                $integrations->{self::INTEGRATION_ID} = (bool) $current->get('enabled');
                $changed = true;
            }
        }

        foreach ($this->getLegacyIntegrationIdList() as $legacyId) {
            if (property_exists($integrations, $legacyId)) {
                unset($integrations->{$legacyId});
                $changed = true;
            }
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $integrations);
        $configWriter->save();
    }

    private function normalizeConfigIntegrations(mixed $integrations): stdClass
    {
        if ($integrations instanceof stdClass) {
            return clone $integrations;
        }

        if (is_array($integrations)) {
            return (object) $integrations;
        }

        return (object) [];
    }

    private function cloneEntity(
        EntityManager $entityManager,
        string $entityType,
        Entity $source,
        string $targetId,
    ): Entity {
        $values = get_object_vars($source->getValueMap());
        $values['id'] = $targetId;

        return $entityManager->createEntity($entityType, $values);
    }

    private function copyMissingValues(Entity $target, Entity $source): void
    {
        foreach (get_object_vars($source->getValueMap()) as $attribute => $value) {
            if ($attribute === 'id') {
                continue;
            }

            if ($attribute === 'enabled' && $value === true && $target->get($attribute) !== true) {
                $target->set($attribute, true);

                continue;
            }

            if ($target->has($attribute) && $target->get($attribute) !== null && $target->get($attribute) !== '') {
                continue;
            }

            $target->set($attribute, $value);
        }
    }

    /**
     * @return string[]
     */
    private function getLegacyIntegrationIdList(): array
    {
        return [
            self::LEGACY_GOOGLE_INTEGRATION_ID,
            self::LEGACY_SAFEHOUSE_GOOGLE_ID,
        ];
    }
}
