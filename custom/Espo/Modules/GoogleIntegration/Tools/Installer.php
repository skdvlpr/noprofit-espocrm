<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\ORM\EntityManager;

/**
 * Post-install for the standalone Google Calendar & Drive extension.
 *
 * - Ensures an {@see Integration} DB row exists for {@see self::INTEGRATION_ID}
 *   (disabled by default) so Admin → Integrations can open the panel.
 * - Removes the legacy `GoogleSafehouse` row if present (previously shipped
 *   inside SafehouseCrm before this extension was split out).
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
        $this->removeLegacyIntegrationRow($em);
        $this->ensureIntegrationRow($em);
        $container->getByClass(DataManager::class)->rebuild();
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
