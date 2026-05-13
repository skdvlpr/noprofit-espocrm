<?php

namespace Espo\Modules\SafehouseCrm\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

/**
 * Shared post-install / provisioning logic invoked from both extension
 * entrypoints:
 *
 *   - {@see \AfterInstall} in `scripts/AfterInstall.php` — the script Espo
 *     Extension Manager runs after a ZIP install.
 *   - {@see \Espo\Modules\SafehouseCrm\AfterInstall} — the in-module class used
 *     when the module ships pre-installed (e.g. during dev workflows).
 *
 * Keeping the logic here ensures both flows hide Lead/Case from navigation,
 * surface Safehouse custom tabs in the `$CRM` block, provision the canonical
 * roles + Administration team, and rebuild metadata.
 *
 * The class is intentionally Container-based (rather than constructor DI) so
 * it can be invoked from `scripts/AfterInstall.php`, which receives a bare
 * {@see Container}.
 */
class Installer
{
    private const DOMAIN_ENTITIES = [
        'VolunteerEmployee',
        'Member',
        'MealCount',
    ];

    private const OTHER_TABS_TO_ENSURE = ['Account', 'Opportunity', 'Document'];

    private const ENTITIES_TO_HIDE = ['Lead', 'Case'];

    private const CRM_DIVIDER_TEXT = '$CRM';

    private const ANCHOR_BEFORE = 'Contact';

    public function runPostInstall(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $config->get('tabList', []) ?? [];
        $quickCreateList = $config->get('quickCreateList', []) ?? [];

        foreach (array_merge(self::DOMAIN_ENTITIES, self::OTHER_TABS_TO_ENSURE) as $item) {
            if (!in_array($item, $tabList, true)) {
                $tabList[] = $item;
            }
        }

        $tabList = $this->removeEntitiesFromList($tabList, self::ENTITIES_TO_HIDE);
        $tabList = $this->reorderDomainEntitiesIntoCrmBlock($tabList, self::DOMAIN_ENTITIES);

        $quickCreateList = $this->removeEntitiesFromList($quickCreateList, self::ENTITIES_TO_HIDE);

        $configWriter->set('tabList', $tabList);
        $configWriter->set('quickCreateList', $quickCreateList);
        $configWriter->save();

        $roleSetup = $injectableFactory->create(RoleSetup::class);
        $roleSetup->provisionRoles();
        $roleSetup->provisionTeams();

        $container->getByClass(DataManager::class)->rebuild();
    }

    /**
     * @param array<int, mixed> $list
     * @param string[] $entities
     * @return array<int, mixed>
     */
    private function removeEntitiesFromList(array $list, array $entities): array
    {
        return array_values(array_filter(
            $list,
            static function ($item) use ($entities): bool {
                return !(is_string($item) && in_array($item, $entities, true));
            }
        ));
    }

    /**
     * Move the domain entities into the top `$CRM` navbar section, placing them
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

        $contactIndex = null;
        $crmDividerIndex = null;

        foreach ($without as $i => $item) {
            if (is_string($item) && $item === self::ANCHOR_BEFORE) {
                $contactIndex = $i;
            }
            if (
                $crmDividerIndex === null
                && is_object($item)
                && ($item->type ?? null) === 'divider'
                && ($item->text ?? null) === self::CRM_DIVIDER_TEXT
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
