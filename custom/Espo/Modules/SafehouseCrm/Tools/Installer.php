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
 * Keeping the logic here ensures both flows hide Case from navigation, restore
 * Lead in the `$CRM` block, place reporting entities in a native navbar group
 * tab (`type: group` dropdown — works in horizontal and vertical navbars),
 * surface Safehouse custom tabs, provision the canonical roles + Administration
 * team, and rebuild metadata.
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
    ];

    private const REPORTING_ENTITIES = [
        'MealCount',
        'AssociationMealCount',
    ];

    private const OTHER_TABS_TO_ENSURE = ['Account', 'Opportunity', 'Document'];

    private const ENTITIES_TO_HIDE = ['Case'];

    /** Placed in `$CRM` immediately after Contact (when present). */
    private const LEAD_NAV_TAB = 'Lead';

    private const CRM_DIVIDER_TEXT = '$CRM';

    /** Navbar group label key — translated via Global.json → navbarTabs.Rendicontazione */
    private const REPORTING_GROUP_TEXT = '$Rendicontazione';

    private const ANCHOR_BEFORE = 'Contact';

    /** Reporting dropdown sits after F&F (Opportunity), not inside it. */
    private const REPORTING_GROUP_ANCHOR_AFTER = 'Opportunity';

    public function runPostInstall(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $config->get('tabList', []) ?? [];
        $quickCreateList = $config->get('quickCreateList', []) ?? [];

        foreach (
            array_merge(
                [self::LEAD_NAV_TAB],
                self::DOMAIN_ENTITIES,
                self::REPORTING_ENTITIES,
                self::OTHER_TABS_TO_ENSURE
            ) as $item
        ) {
            if (!in_array($item, $tabList, true)) {
                $tabList[] = $item;
            }
        }

        $tabList = $this->removeEntitiesFromList($tabList, self::ENTITIES_TO_HIDE);
        $tabList = $this->reorderCrmNavbarBlock($tabList);
        $tabList = $this->reorderReportingNavbarBlock($tabList);

        $quickCreateList = $this->removeEntitiesFromList($quickCreateList, self::ENTITIES_TO_HIDE);

        if (!in_array(self::LEAD_NAV_TAB, $quickCreateList, true)) {
            $quickCreateList[] = self::LEAD_NAV_TAB;
        }

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
     * Move Lead + CRM domain entities into the top `$CRM` navbar section:
     * Contact → Lead → VolunteerEmployee → Member.
     *
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function reorderCrmNavbarBlock(array $tabList): array
    {
        $crmEntities = array_merge([self::LEAD_NAV_TAB], self::DOMAIN_ENTITIES);

        $without = array_values(array_filter(
            $tabList,
            static function ($item) use ($crmEntities): bool {
                return !(is_string($item) && in_array($item, $crmEntities, true));
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
            $crmEntities,
            array_slice($without, $insertIndex)
        );
    }

    /**
     * Place reporting entities in a native Espo navbar group tab (`type: group`).
     * Dropdown works in horizontal and vertical navbars. Only reporting entities
     * belong in itemList — Opportunity (F&F) stays a top-level CRM tab.
     *
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function reorderReportingNavbarBlock(array $tabList): array
    {
        $reportingEntities = self::REPORTING_ENTITIES;

        $without = array_values(array_filter(
            $tabList,
            static function ($item) use ($reportingEntities): bool {
                if (is_string($item) && in_array($item, $reportingEntities, true)) {
                    return false;
                }

                if (!is_object($item)) {
                    return true;
                }

                $type = $item->type ?? null;
                $text = $item->text ?? null;

                if ($type === 'divider' && $text === self::REPORTING_GROUP_TEXT) {
                    return false;
                }

                return !($type === 'group' && $text === self::REPORTING_GROUP_TEXT);
            }
        ));

        $insertIndex = $this->resolveReportingGroupInsertIndex($without);

        $group = (object) [
            'type' => 'group',
            'text' => self::REPORTING_GROUP_TEXT,
            'iconClass' => 'fas fa-chart-bar',
            'color' => '#c4886b',
            'itemList' => $reportingEntities,
        ];

        return array_merge(
            array_slice($without, 0, $insertIndex),
            [$group],
            array_slice($without, $insertIndex)
        );
    }

    /**
     * @param array<int, mixed> $tabList
     */
    private function resolveReportingGroupInsertIndex(array $tabList): int
    {
        foreach ($tabList as $i => $item) {
            if ($item === self::REPORTING_GROUP_ANCHOR_AFTER) {
                return $i + 1;
            }
        }

        foreach ($tabList as $i => $item) {
            if ($item === 'Member') {
                return $i + 1;
            }
        }

        return count($tabList);
    }
}
