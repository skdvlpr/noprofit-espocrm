<?php

namespace Espo\Modules\GoogleIntegration\Tools;

use Espo\Core\Utils\Config;
use Espo\Entities\Integration as IntegrationEntity;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Espo\ORM\EntityManager;

/**
 * Whether the GoogleIntegration extension is enabled for this Espo instance.
 */
class IntegrationState
{
    public function __construct(
        private Config $config,
        private EntityManager $entityManager,
    ) {}

    public function isGoogleIntegrationEnabled(): bool
    {
        $integrations = $this->config->get('integrations');
        $configFlag = false;

        if (is_object($integrations)) {
            $configFlag = (bool) ($integrations->{Installer::INTEGRATION_ID} ?? false);
        } elseif (is_array($integrations)) {
            $configFlag = (bool) ($integrations[Installer::INTEGRATION_ID] ?? false);
        }

        if (!$configFlag) {
            return false;
        }

        $integration = $this->entityManager
            ->getEntityById(IntegrationEntity::ENTITY_TYPE, Installer::INTEGRATION_ID);

        return $integration !== null && $integration->get('enabled');
    }
}
