<?php

namespace Espo\Modules\VolunteerActivityDispatch\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

/**
 * Post-install: ensure ActivityOffer tab + rebuild metadata/cache.
 */
class Installer
{
    public function runPostInstall(Container $container): void
    {
        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        /** @var Config $config */
        $config = $container->getByClass(Config::class);
        /** @var ConfigWriter $configWriter */
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $config->get('tabList', []) ?? [];

        if (!in_array('ActivityOffer', $tabList, true)) {
            $tabList[] = 'ActivityOffer';
            $configWriter->set('tabList', $tabList);
            $configWriter->save();
        }

        /** @var DataManager $dataManager */
        $dataManager = $container->getByClass(DataManager::class);
        $dataManager->rebuild();
    }
}
