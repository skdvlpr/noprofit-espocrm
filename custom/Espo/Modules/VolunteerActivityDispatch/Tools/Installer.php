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

        $this->migrateLegacyPlaceVarchar($container);

        /** @var DataManager $dataManager */
        $dataManager = $container->getByClass(DataManager::class);
        $dataManager->rebuild();
    }

    /**
     * One-shot: old `place` varchar → `place_city` before address columns replace it.
     */
    private function migrateLegacyPlaceVarchar(Container $container): void
    {
        try {
            /** @var \Espo\ORM\EntityManager $em */
            $em = $container->getByClass(\Espo\ORM\EntityManager::class);
            $pdo = $em->getPDO();

            $hasPlace = (bool) $pdo->query(
                "SHOW COLUMNS FROM `activity_offer_slot` LIKE 'place'"
            )->fetch();

            if (!$hasPlace) {
                return;
            }

            $hasCity = (bool) $pdo->query(
                "SHOW COLUMNS FROM `activity_offer_slot` LIKE 'place_city'"
            )->fetch();

            if ($hasCity) {
                $pdo->exec(
                    "UPDATE `activity_offer_slot`
                     SET `place_city` = `place`
                     WHERE (`place_city` IS NULL OR `place_city` = '')
                       AND `place` IS NOT NULL AND `place` <> ''"
                );
            }
        } catch (\Throwable) {
            // Table may not exist yet on first install — rebuild creates schema.
        }
    }
}
