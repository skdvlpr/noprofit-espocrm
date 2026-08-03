<?php

namespace Espo\Modules\VolunteerActivityDispatch\Core\Rebuild;

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Rebuild\RebuildAction;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\Log;
use Espo\Modules\VolunteerActivityDispatch\Tools\Installer;
use Espo\Tools\LayoutManager\LayoutManager;

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
    /** Bump when new provisioning steps must re-run on production rebuild. */
    private const PROVISION_VERSION = '2026-08-03-shift-planning-v7';
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
        $installer->normalizeInviteOfferLinks($this->container);
        $installer->ensureRoleAccess($this->container);
        $installer->ensureUserCompetencesLayout($this->container, $this->injectableFactory);
        $installer->ensureEmailTemplates($this->container);

        if (!$this->userDetailLayoutReady()) {
            /** @var Log $log */
            $log = $this->container->getByClass(Log::class);
            $log->warning(
                'VolunteerActivityDispatch provision deferred: User detail layout missing activityCompetences or isOccasional'
            );

            return;
        }

        $configWriter = $this->injectableFactory->create(ConfigWriter::class);
        $configWriter->set(self::CONFIG_KEY, self::PROVISION_VERSION);
        $configWriter->save();
    }

    private function userDetailLayoutReady(): bool
    {
        /** @var LayoutManager $layoutManager */
        $layoutManager = $this->injectableFactory->create(LayoutManager::class);
        $raw = (string) ($layoutManager->get('User', 'detail') ?? '');

        return str_contains($raw, 'activityCompetences') && str_contains($raw, 'isOccasional');
    }
}
