<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

// This script simulates the AfterInstall tab addition for local dev
return function (Container $container) {
    /** @var Config $config */
    $config = $container->getByClass(Config::class);

    /** @var InjectableFactory $injectableFactory */
    $injectableFactory = $container->get('injectableFactory');

    /** @var ConfigWriter $configWriter */
    $configWriter = $injectableFactory->create(ConfigWriter::class);

    $tabList = $config->get('tabList', []);
    $entityName = 'ConteggioPasti';

    if (in_array($entityName, $tabList, true)) {
        echo "Tab '$entityName' already exists.\n";
        return;
    }

    // Insert after Associati if exists
    $afterIndex = array_search('Associati', $tabList, true);
    if ($afterIndex !== false) {
        array_splice($tabList, $afterIndex + 1, 0, [$entityName]);
    } else {
        $tabList[] = $entityName;
    }

    $configWriter->set('tabList', $tabList);
    $configWriter->save();

    echo "Tab '$entityName' added to tabList.\n";
};
