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
 * - Migrates legacy integration ids if present (previously shipped inside
 *   SafehouseCrm and then as `GoogleIntegration` before the UI rename).
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

        $em->getTransactionManager()->run(function () use ($em): void {
            $this->migrateLegacyIntegrationRows($em);
            $this->migrateLegacyExternalAccountRows($em);
            $this->removeLegacyIntegrationRows($em);
            $this->ensureIntegrationRow($em);
        });

        $this->migrateLegacyConfigFlag(
            $container->getByClass(Config::class),
            $container->getByClass(InjectableFactory::class)->create(ConfigWriter::class),
        );

        $container->getByClass(DataManager::class)->rebuild();
    }

    private function migrateLegacyIntegrationRows(EntityManager $entityManager): void
    {
        if ($this->findStoredEntity($entityManager, IntegrationEntity::ENTITY_TYPE, self::INTEGRATION_ID) !== null) {
            return;
        }

        foreach ($this->legacyIntegrationIdList() as $legacyId) {
            $legacy = $this->findStoredEntity($entityManager, IntegrationEntity::ENTITY_TYPE, $legacyId);

            if ($legacy === null) {
                continue;
            }

            $entityManager->createEntity(
                IntegrationEntity::ENTITY_TYPE,
                $this->cloneEntityData($legacy, self::INTEGRATION_ID),
            );

            return;
        }
    }

    private function migrateLegacyExternalAccountRows(EntityManager $entityManager): void
    {
        $repo = $entityManager->getRDBRepository(ExternalAccountEntity::ENTITY_TYPE);

        foreach ($this->legacyIntegrationIdList() as $legacyId) {
            $legacyList = $repo
                ->where(['id*' => $legacyId . '__%'])
                ->find();

            foreach ($legacyList as $legacy) {
                $legacyAccountId = $legacy->getId();
                $prefix = $legacyId . '__';

                if (!str_starts_with($legacyAccountId, $prefix)) {
                    continue;
                }

                $userId = substr($legacyAccountId, strlen($prefix));

                if ($userId === '') {
                    continue;
                }

                $newId = self::INTEGRATION_ID . '__' . $userId;

                if ($this->findStoredEntity($entityManager, ExternalAccountEntity::ENTITY_TYPE, $newId) === null) {
                    $entityManager->createEntity(
                        ExternalAccountEntity::ENTITY_TYPE,
                        $this->cloneEntityData($legacy, $newId),
                    );
                }

                $entityManager->removeEntity($legacy);
            }
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

        if ($legacyList === []) {
            return;
        }

        foreach ($legacyList as $legacy) {
            $entityManager->removeEntity($legacy);
        }
    }

    private function migrateLegacyConfigFlag(Config $config, ConfigWriter $configWriter): void
    {
        $integrations = $config->get('integrations');
        $data = $integrations instanceof \stdClass ? clone $integrations : (object) [];

        if (is_array($integrations)) {
            $data = (object) $integrations;
        }

        $changed = false;
        $hasCanonical = property_exists($data, self::INTEGRATION_ID);

        foreach ($this->legacyIntegrationIdList() as $legacyId) {
            if (!property_exists($data, $legacyId)) {
                continue;
            }

            if (!$hasCanonical) {
                $data->{self::INTEGRATION_ID} = (bool) $data->{$legacyId};
                $hasCanonical = true;
            }

            unset($data->{$legacyId});
            $changed = true;
        }

        if (!$changed) {
            return;
        }

        $configWriter->set('integrations', $data);
        $configWriter->save();
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
     * Repositories for Integration and ExternalAccount synthesize new entities
     * for missing ids, so use RDBRepository lookups when existence matters.
     */
    private function findStoredEntity(EntityManager $entityManager, string $entityType, string $id): ?Entity
    {
        return $entityManager
            ->getRDBRepository($entityType)
            ->where(['id' => $id])
            ->findOne();
    }

    /**
     * @return array<string, mixed>
     */
    private function cloneEntityData(Entity $source, string $newId): array
    {
        $data = get_object_vars($source->getValueMap());
        $data['id'] = $newId;

        return $data;
    }

    /**
     * @return string[]
     */
    private function legacyIntegrationIdList(): array
    {
        return [
            self::LEGACY_GOOGLE_INTEGRATION_ID,
            self::LEGACY_SAFEHOUSE_GOOGLE_ID,
        ];
    }
}
