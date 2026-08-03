<?php

namespace Espo\Modules\VolunteerActivityDispatch\Core\Rebuild;

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Rebuild\RebuildAction;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\VolunteerActivityDispatch\Tools\Installer;

/**
 * One-shot provisioning on rebuild, version-gated via config.
 *
 * Production deploys only rsync + rebuild (no extension install step), so
 * data migrations and role/layout provisioning must ride on rebuild.
 * Bump PROVISION_VERSION when shipping new provisioning steps.
 *
 * @noinspection PhpUnused
 */
class ProvisionShiftPlanning implements RebuildAction
{
    private const PROVISION_VERSION = '2026-08-03-shift-planning-v1';
    private const CONFIG_KEY = 'vadProvisionVersion';

    public function __construct(
        private Config $config,
        private Container $container,
        private InjectableFactory $injectableFactory
    ) {}

    public function process(): void
    {
        if ($this->config->get(self::CONFIG_KEY) === self::PROVISION_VERSION) {
            return;
        }

        $installer = new Installer();

        $installer->migrateShiftPlanningStatuses($this->container);
        $installer->ensureRoleAccess($this->container);
        $installer->ensureUserCompetencesLayout($this->container, $this->injectableFactory);

        $configWriter = $this->injectableFactory->create(ConfigWriter::class);
        $configWriter->set(self::CONFIG_KEY, self::PROVISION_VERSION);
        $configWriter->save();
    }
}
