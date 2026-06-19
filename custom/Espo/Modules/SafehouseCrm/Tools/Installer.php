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
 * Lead in the `$CRM` block, place reporting entities under `$Rendicontazione`,
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
    ];

    private const OTHER_TABS_TO_ENSURE = ['Account', 'Opportunity', 'Document'];

    private const ENTITIES_TO_HIDE = ['Case'];

    /** Placed in `$CRM` immediately after Contact (when present). */
    private const LEAD_NAV_TAB = 'Lead';

    private const CRM_DIVIDER_TEXT = '$CRM';

    private const REPORTING_DIVIDER_TEXT = '$Rendicontazione';

    private const REPORTING_DIVIDER_ID = '748201';

    private const ANCHOR_BEFORE = 'Contact';

    private const REPORTING_ANCHOR_AFTER = 'Member';

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
     * Place reporting entities under a `$Rendicontazione` navbar group
     * immediately after Member (fallback: after last CRM domain entity).
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

                return !(
                    is_object($item)
                    && ($item->type ?? null) === 'divider'
                    && ($item->text ?? null) === self::REPORTING_DIVIDER_TEXT
                );
            }
        ));

        $insertIndex = $this->resolveReportingInsertIndex($without);

        $divider = (object) [
            'type' => 'divider',
            'text' => self::REPORTING_DIVIDER_TEXT,
            'id' => self::REPORTING_DIVIDER_ID,
        ];

        $block = array_merge([$divider], $reportingEntities);

        return array_merge(
            array_slice($without, 0, $insertIndex),
            $block,
            array_slice($without, $insertIndex)
        );
    }

    /**
     * @param array<int, mixed> $tabList
     */
    private function resolveReportingInsertIndex(array $tabList): int
    {
        foreach ($tabList as $i => $item) {
            if (is_string($item) && $item === self::REPORTING_ANCHOR_AFTER) {
                return $i + 1;
            }
        }

        $crmEntities = array_merge([self::LEAD_NAV_TAB], self::DOMAIN_ENTITIES);

        for ($i = count($tabList) - 1; $i >= 0; $i--) {
            $item = $tabList[$i];
            if (is_string($item) && in_array($item, $crmEntities, true)) {
                return $i + 1;
            }
        }

        return count($tabList);
    }
}
