<?php

namespace Espo\Modules\SafehouseCrm;

use Espo\Core\Application;

class AfterInstall
{
    public function run(Application $app): void
    {
        $container = $app->getContainer();
        $config = $container->get('config');
        
        $tabList = $config->get('tabList', []);
        
        $toAdd = [
            'Account',
            'FondiSovvenzioni',
            'VolontarioDipendente',
            'Associati',
            'ConteggioPasti',
            'Documents'
        ];
        
        $changed = false;
        foreach ($toAdd as $item) {
            if (!in_array($item, $tabList)) {
                $tabList[] = $item;
                $changed = true;
            }
        }
        
        if ($changed) {
            $config->set('tabList', $tabList);
            $config->save();
        }
        
        $container->get('dataManager')->rebuild();
    }
}
