<?php

namespace Espo\Modules\SafehouseCrm;

use Espo\Core\Application;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\SafehouseCrm\Tools\RoleSetup;

class AfterInstall
{
    public function run(Application $app): void
    {
        $container = $app->getContainer();
        $config = $container->get('config');

        $injectableFactory = $container->get('injectableFactory');
        if (!$injectableFactory instanceof InjectableFactory) {
            $injectableFactory = $container->getByClass(InjectableFactory::class);
        }

        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $config->get('tabList', []) ?? [];
        $quickCreateList = $config->get('quickCreateList', []) ?? [];

        $domainEntities = [
            'VolontarioDipendente',
            'Associati',
            'ConteggioPasti',
        ];

        $otherToEnsure = ['Account', 'FondiSovvenzioni', 'Documents'];

        $entitiesToHide = ['Lead', 'Case'];

        foreach (array_merge($domainEntities, $otherToEnsure) as $item) {
            if (!in_array($item, $tabList, true)) {
                $tabList[] = $item;
            }
        }

        $tabList = array_values(array_filter(
            $tabList,
            static function ($item) use ($entitiesToHide): bool {
                return !(is_string($item) && in_array($item, $entitiesToHide, true));
            }
        ));

        $tabList = $this->reorderDomainEntitiesIntoCrmBlock($tabList, $domainEntities);

        $quickCreateList = array_values(array_filter(
            $quickCreateList,
            static function ($item) use ($entitiesToHide): bool {
                return !(is_string($item) && in_array($item, $entitiesToHide, true));
            }
        ));

        $configWriter->set('tabList', $tabList);
        $configWriter->set('quickCreateList', $quickCreateList);
        $configWriter->save();

        $roleSetup = $injectableFactory->create(RoleSetup::class);
        $roleSetup->provisionRoles();

        $container->get('dataManager')->rebuild();
    }

    /**
     * Move the given entities into the top "$CRM" navbar section, placing them
     * right after `Contact` (or right after the `$CRM` divider if `Contact` is
     * absent, or at the head if neither is present).
     *
     * @param array<int, mixed> $tabList
     * @param string[] $domainEntities
     * @return array<int, mixed>
     */
    private function reorderDomainEntitiesIntoCrmBlock(array $tabList, array $domainEntities): array
    {
        $without = array_values(array_filter(
            $tabList,
            static function ($item) use ($domainEntities): bool {
                return !(is_string($item) && in_array($item, $domainEntities, true));
            }
        ));

        $insertIndex = null;
        $contactIndex = null;
        $crmDividerIndex = null;

        foreach ($without as $i => $item) {
            if (is_string($item) && $item === 'Contact') {
                $contactIndex = $i;
            }
            if (
                $crmDividerIndex === null
                && is_object($item)
                && ($item->type ?? null) === 'divider'
                && ($item->text ?? null) === '$CRM'
            ) {
                $crmDividerIndex = $i;
            }
        }

        if ($contactIndex !== null) {
            $insertIndex = $contactIndex + 1;
        } elseif ($crmDividerIndex !== null) {
            $insertIndex = $crmDividerIndex + 1;
        } else {
            $insertIndex = 0;
        }

        return array_merge(
            array_slice($without, 0, $insertIndex),
            $domainEntities,
            array_slice($without, $insertIndex)
        );
    }
}
