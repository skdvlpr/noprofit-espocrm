<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Rebuild\RebuildAction;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\NonprofitEspocrm\Tools\RoleSetup;

/**
 * Apply canonical Admin / Volunteer / Member ACL on rebuild (version-gated).
 * Safe for production: overwrites only when ACL_MATRIX_VERSION changes.
 *
 * @noinspection PhpUnused
 */
class ProvisionRoleAcl implements RebuildAction
{
    public function __construct(
        private Config $config,
        private InjectableFactory $injectableFactory,
        private DataManager $dataManager
    ) {}

    public function process(): void
    {
        if ($this->config->get(RoleSetup::ACL_MATRIX_CONFIG_KEY) === RoleSetup::ACL_MATRIX_VERSION) {
            return;
        }

        /** @var RoleSetup $roleSetup */
        $roleSetup = $this->injectableFactory->create(RoleSetup::class);
        $roleSetup->provisionRoles(true);
        $roleSetup->pruneNonCoreRoles();
        $roleSetup->provisionTeams();

        $configWriter = $this->injectableFactory->create(ConfigWriter::class);
        $configWriter->set(RoleSetup::ACL_MATRIX_CONFIG_KEY, RoleSetup::ACL_MATRIX_VERSION);
        $configWriter->set('safehouseStripeSyncUserNames', RoleSetup::STRIPE_SYNC_USER_NAMES);
        $configWriter->save();

        $this->dataManager->clearCache();
    }
}
