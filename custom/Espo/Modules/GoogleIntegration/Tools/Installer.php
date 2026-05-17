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
use Espo\ORM\Repository\Option\SaveOption;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Migrates legacy integration rows/config/user accounts to the current id.
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
    private const LEGACY_INTEGRATION_ID_LIST = [
        self::LEGACY_GOOGLE_INTEGRATION_ID,
        self::LEGACY_SAFEHOUSE_GOOGLE_ID,
    ];

    public function runPostInstall(Container $container): void
    {
        $em = $container->getByClass(EntityManager::class);
        $injectableFactory = $container->getByClass(InjectableFactory::class);

        $this->migrateLegacyIntegrationRows($em);
        $this->migrateLegacyExternalAccountRows($em);
        $this->migrateLegacyIntegrationConfig(
            $container->getByClass(Config::class),
            $injectableFactory->create(ConfigWriter::class)
        );
        $this->ensureIntegrationRow($em);
        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationRows(EntityManager $entityManager): void
    {
        $target = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID);

        foreach (self::LEGACY_INTEGRATION_ID_LIST as $legacyId) {
            $legacy = $entityManager->getEntityById(IntegrationEntity::ENTITY_TYPE, $legacyId);

            if ($legacy === null) {
                continue;
            }

            if ($target === null) {
                $target = $entityManager->createEntity(
                    IntegrationEntity::ENTITY_TYPE,
                    $this->getCopiedValueMap($legacy, self::INTEGRATION_ID)
                );
            } else {
                $this->copyMissingValues($target, $legacy);
            }

            $entityManager->saveEntity($target, [SaveOption::SKIP_ALL => true]);
            $entityManager->removeEntity($legacy, [SaveOption::SKIP_ALL => true]);
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
        $repo = $entityManager->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE);

        foreach (self::LEGACY_INTEGRATION_ID_LIST as $legacyId) {
            $legacyList = $repo
                ->where(['id*' => $legacyId . '__%'])
                ->find();

            foreach ($legacyList as $legacy) {
                $id = $legacy->getId();

                if (!is_string($id)) {
                    continue;
                }

                $userId = substr($id, strlen($legacyId . '__'));

                if ($userId === '') {
                    continue;
                }

                $targetId = self::INTEGRATION_ID . '__' . $userId;
                $target = $entityManager->getEntityById(ExternalAccountEntity::ENTITY_TYPE, $targetId);

                if ($target === null) {
                    $target = $entityManager->createEntity(
                        ExternalAccountEntity::ENTITY_TYPE,
                        $this->getCopiedValueMap($legacy, $targetId)
                    );
                } else {
                    $this->copyMissingValues($target, $legacy);
                }

                $entityManager->saveEntity($target, [SaveOption::SKIP_ALL => true]);
                $entityManager->removeEntity($legacy, [SaveOption::SKIP_ALL => true]);
            }
        }
    }

    private function migrateLegacyIntegrationConfig(Config $config, ConfigWriter $configWriter): void
    {
        $integrations = $config->get('integrations');

        if (!is_object($integrations) && !is_array($integrations)) {
            return;
        }

        $data = is_object($integrations) ? clone $integrations : (object) $integrations;
        $changed = false;

        if (!property_exists($data, self::INTEGRATION_ID)) {
            foreach (self::LEGACY_INTEGRATION_ID_LIST as $legacyId) {
                if (!property_exists($data, $legacyId)) {
                    continue;
                }

                $data->{self::INTEGRATION_ID} = (bool) $data->$legacyId;
                $changed = true;

                break;
            }
        }

        foreach (self::LEGACY_INTEGRATION_ID_LIST as $legacyId) {
            if (!property_exists($data, $legacyId)) {
                continue;
            }

            unset($data->$legacyId);
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $data);
        $configWriter->save();
        $config->update();
    }

    /**
     * @return array<string, mixed>
     */
    private function getCopiedValueMap(IntegrationEntity $source, string $id): array
    {
        $map = get_object_vars($source->getValueMap());
        $map['id'] = $id;

        return $map;
    }

    private function copyMissingValues(IntegrationEntity $target, IntegrationEntity $source): void
    {
        foreach (get_object_vars($source->getValueMap()) as $attribute => $value) {
            if ($attribute === 'id' || $value === null || $value === '') {
                continue;
            }

            $current = $target->get($attribute);

            if ($current !== null && $current !== '') {
                continue;
            }

            $target->set($attribute, $value);
        }
    }
}
