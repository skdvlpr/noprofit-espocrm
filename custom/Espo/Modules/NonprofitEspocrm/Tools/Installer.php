<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Container;
use Espo\Core\DataManager;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Modules\NonprofitEspocrm\Tools\Calendar\SafehouseGoogleCalendarProvisioner;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningInstaller;

/**
 * Shared post-install / provisioning logic invoked from both extension
 * entrypoints:
 *
 *   - {@see \AfterInstall} in `scripts/AfterInstall.php` — the script Espo
 *     Extension Manager runs after a ZIP install.
 *   - {@see \Espo\Modules\NonprofitEspocrm\AfterInstall} — the in-module class used
 *     when the module ships pre-installed (e.g. during dev workflows).
 *
 * Keeping the logic here ensures both flows surface intake records (Case) in the
 * `$CRM` / Principali block immediately before `$Rendicontazione`, enforce the
 * native navbar group tab (`type: group` dropdown — works in horizontal and
 * vertical navbars), surface Safehouse custom tabs, apply the Safehouse Aurora
 * default theme on fresh/stock-theme installs, provision the canonical roles +
 * Administration team, and rebuild metadata.
 *
 * The class is intentionally Container-based (rather than constructor DI) so
 * it can be invoked from `scripts/AfterInstall.php`, which receives a bare
 * {@see Container}.
 */
class Installer
{
    private const DOMAIN_ENTITIES = [
    ];

    private const REPORTING_ENTITIES = [
        'MealCount',
        'AssociationMealCount',
        'PrimaNota',
    ];

    private const OTHER_TABS_TO_ENSURE = [
        'Account',
        'Opportunity',
        'Document',
        'Case',
        'Intervention',
        'FoodParcelRegistration',
    ];

    /** Retired Contact-STI legacy entities — never restore to tabList. */
    private const ENTITIES_TO_HIDE_DEFAULT = [
        'VolunteerEmployee',
        'Member',
    ];

    private const SUPPORT_DIVIDER_TEXT = '$Support';

    /** Support block: knowledge base only (Case lives in Principali). */
    private const SUPPORT_NAV_ORDER = ['KnowledgeBaseArticle'];

    private const LEAD_NAV_TAB = 'Lead';

    /**
     * Canonical `$CRM` / Principali navbar order.
     * Contact is replaced by Contatti group (All / Volunteers+Employees / Associati).
     *
     * @var string[]
     */
    private const CRM_NAV_ORDER = [
        'Lead',
        'Account',
        'Opportunity',
    ];

    private const CONTACTS_GROUP_TEXT = '$Contatti';

    private const SAFEHOUSE_THEMES = [
        'SafehouseAurora',
        'SafehouseAuroraLight',
    ];

    private const DEFAULT_THEME = 'SafehouseAuroraLight';

    private const APPLICATION_NAME = 'Non profit CRM';

    /** Stock Espo themes replaced when Safehouse is installed (unless user already picked Safehouse). */
    private const LEGACY_DEFAULT_THEMES = [
        'Espo',
        'Light',
        'Dark',
        'Hazyblue',
        'Violet',
        'Glass',
        'Sakura',
        'Flat',
    ];

    private const CRM_DIVIDER_TEXT = '$CRM';

    /** Navbar group label key — translated via Global.json → navbarTabs.Rendicontazione */
    private const REPORTING_GROUP_TEXT = '$Rendicontazione';

    /** Reporting group follows Contatti/Opportunity; Case is inserted before the group afterward. */
    private const REPORTING_GROUP_ANCHOR_AFTER = 'Opportunity';

    /** @return string[] */
    private static function entitiesToHide(): array
    {
        if (getenv('SAFEHOUSE_RESTORE_LEGACY_PARTY_TABS') === '1') {
            return [];
        }

        return self::ENTITIES_TO_HIDE_DEFAULT;
    }

    /**
     * Refresh only the Contatti navbar group (All / Vol+Dip / Occasionali / Associati).
     * Safe to call from rebuild actions — does not touch roles or trigger nested rebuild.
     */
    public function refreshContactsNavbar(Container $container): void
    {
        $config = $container->getByClass(Config::class);
        $injectableFactory = $container->getByClass(InjectableFactory::class);
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $this->reorderContactsNavbarBlock($config->get('tabList', []) ?? []);
        $configWriter->set('tabList', $tabList);
        $configWriter->save();
    }

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
                self::OTHER_TABS_TO_ENSURE,
                ['Contact']
            ) as $item
        ) {
            if (!in_array($item, $tabList, true)) {
                $tabList[] = $item;
            }
        }

        $tabList = $this->removeEntitiesFromList($tabList, self::entitiesToHide());
        $tabList = $this->reorderCrmNavbarBlock($tabList);
        $tabList = $this->reorderContactsNavbarBlock($tabList);
        $tabList = $this->reorderReportingNavbarBlock($tabList);
        $tabList = $this->reorderCaseBeforeReportingGroup($tabList);
        $tabList = $this->reorderSupportNavbarBlock($tabList);

        $shiftPlanningInstaller = new ShiftPlanningInstaller();
        $tabList = $shiftPlanningInstaller->ensureActivityOfferTab($tabList);

        $quickCreateList = $this->removeEntitiesFromList($quickCreateList, self::entitiesToHide());

        if (!in_array(self::LEAD_NAV_TAB, $quickCreateList, true)) {
            $quickCreateList[] = self::LEAD_NAV_TAB;
        }

        if (!in_array('Case', $quickCreateList, true)) {
            $quickCreateList[] = 'Case';
        }

        $this->provisionDefaultTheme($config, $configWriter);
        $this->provisionApplicationName($config, $configWriter);
        $this->provisionGlobalSearchEntityList($config, $configWriter);
        $this->provisionEmailAddressLookupEntityTypeList($config, $configWriter);
        $this->provisionPrimaNotaOpeningCashDefaults($config, $configWriter);
        $this->provisionInboundEmailCaseTypes($config, $configWriter);

        $configWriter->set('tabList', $tabList);
        $configWriter->set('quickCreateList', $quickCreateList);
        $configWriter->save();

        $roleSetup = $injectableFactory->create(RoleSetup::class);
        $roleSetup->provisionRoles();
        $roleSetup->provisionTeams();
        $roleSetup->ensureWebsiteApiRoleAssignments();

        $configWriter->set('safehouseStripeSyncUserNames', RoleSetup::STRIPE_SYNC_USER_NAMES);
        $configWriter->save();

        $injectableFactory->create(SafehouseGoogleCalendarProvisioner::class)->run($container);
        $injectableFactory->create(\Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelPdfService::class)
            ->provisionTemplate();

        $shiftPlanningInstaller->migrateLegacyPlaceVarchar($container);

        $container->getByClass(DataManager::class)->rebuild();

        $shiftPlanningInstaller->postRebuildProvision($container, $injectableFactory);
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
     * Enforce canonical `$CRM` entity order immediately after the `$CRM` divider.
     * Lead → Contatti group → Account → Opportunity (Member/VE retired).
     *
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function reorderCrmNavbarBlock(array $tabList): array
    {
        $crmEntities = self::CRM_NAV_ORDER;

        $without = array_values(array_filter(
            $tabList,
            static function ($item) use ($crmEntities): bool {
                return !(is_string($item) && in_array($item, $crmEntities, true));
            }
        ));

        $insertIndex = 0;

        foreach ($without as $i => $item) {
            if (
                is_object($item)
                && ($item->type ?? null) === 'divider'
                && ($item->text ?? null) === self::CRM_DIVIDER_TEXT
            ) {
                $insertIndex = $i + 1;

                break;
            }
        }

        return array_merge(
            array_slice($without, 0, $insertIndex),
            $crmEntities,
            array_slice($without, $insertIndex)
        );
    }

    /**
     * Contatti navbar group: All Contacts + Volunteers/Employees + Associati (URL primary filters).
     * Removes bare Contact / legacy Contatti group before re-inserting.
     *
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function reorderContactsNavbarBlock(array $tabList): array
    {
        $without = array_values(array_filter(
            $tabList,
            static function ($item): bool {
                if ($item === 'Contact') {
                    return false;
                }

                if (!is_object($item)) {
                    return true;
                }

                $type = $item->type ?? null;
                $text = $item->text ?? null;
                $name = $item->name ?? null;

                if ($type === 'group' && ($text === self::CONTACTS_GROUP_TEXT || $name === 'Contatti')) {
                    return false;
                }

                // Drop previous URL filter tabs for Contact cohorts.
                if ($type === 'url') {
                    $url = (string) ($item->url ?? '');
                    if (str_contains($url, '#Contact/list/primaryFilter=')) {
                        return false;
                    }
                }

                return true;
            }
        ));

        $insertIndex = 0;

        foreach ($without as $i => $item) {
            if ($item === 'Lead') {
                $insertIndex = $i + 1;
                break;
            }
        }

        if ($insertIndex === 0) {
            foreach ($without as $i => $item) {
                if (
                    is_object($item)
                    && ($item->type ?? null) === 'divider'
                    && ($item->text ?? null) === self::CRM_DIVIDER_TEXT
                ) {
                    $insertIndex = $i + 1;
                    break;
                }
            }
        }

        $group = (object) [
            'type' => 'group',
            'text' => self::CONTACTS_GROUP_TEXT,
            'name' => 'Contatti',
            'iconClass' => 'fas fa-id-badge',
            'color' => '#5b9bd4',
            'itemList' => [
                (object) [
                    'type' => 'url',
                    'text' => '$ContattiAll',
                    'url' => '#Contact',
                    'iconClass' => 'fas fa-address-book',
                ],
                (object) [
                    'type' => 'url',
                    'text' => '$ContattiVolontari',
                    'url' => '#Contact/list/primaryFilter=volunteers',
                    'iconClass' => 'fas fa-hands-helping',
                ],
                (object) [
                    'type' => 'url',
                    'text' => '$ContattiVolontariOccasionali',
                    'url' => '#Contact/list/primaryFilter=volunteersOccasionali',
                    'iconClass' => 'fas fa-user-clock',
                ],
                (object) [
                    'type' => 'url',
                    'text' => '$ContattiDipendenti',
                    'url' => '#Contact/list/primaryFilter=employees',
                    'iconClass' => 'fas fa-user-tie',
                ],
                (object) [
                    'type' => 'url',
                    'text' => '$ContattiAssociati',
                    'url' => '#Contact/list/primaryFilter=associati',
                    'iconClass' => 'fas fa-user-friends',
                ],
            ],
        ];

        return array_merge(
            array_slice($without, 0, $insertIndex),
            [$group],
            array_slice($without, $insertIndex)
        );
    }

    /**
     * Apply Safehouse Aurora Light on fresh installs; keep an explicit Safehouse theme choice.
     */
    private function provisionDefaultTheme(Config $config, ConfigWriter $configWriter): void
    {
        $theme = $config->get('theme');

        if (is_string($theme) && in_array($theme, self::SAFEHOUSE_THEMES, true)) {
            return;
        }

        if (
            $theme === null
            || $theme === false
            || $theme === ''
            || (is_string($theme) && in_array($theme, self::LEGACY_DEFAULT_THEMES, true))
        ) {
            $configWriter->set('theme', self::DEFAULT_THEME);
        }
    }

    /**
     * Brand the instance unless an admin already chose a custom application name.
     */
    private function provisionApplicationName(Config $config, ConfigWriter $configWriter): void
    {
        $current = $config->get('applicationName');

        if (
            is_string($current)
            && $current !== ''
            && $current !== 'EspoCRM'
        ) {
            return;
        }

        $configWriter->set('applicationName', self::APPLICATION_NAME);
    }

    /**
     * Ensure PrimaNota (and Contact) are in navbar global search; strip retired
     * VolunteerEmployee / Member scopes so search never probes deleted entities.
     */
    private function provisionGlobalSearchEntityList(Config $config, ConfigWriter $configWriter): void
    {
        $list = $config->get('globalSearchEntityList') ?? [];

        if (!is_array($list)) {
            $list = [];
        }

        $list = array_values(array_filter($list, 'is_string'));
        $list = $this->removeEntitiesFromList($list, self::entitiesToHide());

        $changed = false;

        foreach (['Contact', 'PrimaNota'] as $entityType) {
            if (!in_array($entityType, $list, true)) {
                $list[] = $entityType;
                $changed = true;
            }
        }

        // Always persist when hide-list may have removed stale VE/Member entries.
        if ($changed || $list !== array_values(array_filter($config->get('globalSearchEntityList') ?? [], 'is_string'))) {
            $configWriter->set('globalSearchEntityList', $list);
        }
    }

    /**
     * Expand EmailAddress/search beyond default [User] so compose + WF To/CC/BCC
     * autocomplete also matches Contact / Lead / Account (same as Select picker).
     */
    private function provisionEmailAddressLookupEntityTypeList(Config $config, ConfigWriter $configWriter): void
    {
        $desired = ['User', 'Contact', 'Lead', 'Account'];
        $list = $config->get('emailAddressLookupEntityTypeList') ?? [];

        if (!is_array($list)) {
            $list = [];
        }

        $list = array_values(array_filter($list, 'is_string'));
        $changed = false;

        foreach ($desired as $entityType) {
            if (!in_array($entityType, $list, true)) {
                $list[] = $entityType;
                $changed = true;
            }
        }

        if ($changed) {
            $configWriter->set('emailAddressLookupEntityTypeList', $list);
        }
    }

    /**
     * Seed Saldo di cassa opening balance keys once (CRM-S16). Admins may
     * override via bin/set-prima-nota-opening-cash.php.
     */
    private function provisionPrimaNotaOpeningCashDefaults(Config $config, ConfigWriter $configWriter): void
    {
        if ($config->get('primaNotaOpeningCashBalance') === null) {
            $configWriter->set('primaNotaOpeningCashBalance', 0.0);
        }
    }

    /**
     * Keep Group Email Account → Case.type map in sync (incl. info@ → RichiestaGenerica).
     */
    private function provisionInboundEmailCaseTypes(Config $config, ConfigWriter $configWriter): void
    {
        $defaults = [
            'sportello.digitale@safehouse.community' => 'SportelloDigitale',
            'sportello.legale@safehouse.community' => 'SportelloLegale',
            'info@safehouse.community' => 'RichiestaGenerica',
        ];

        /** @var array<string, mixed> $current */
        $current = $config->get('inboundEmailCaseTypes') ?? [];
        if (! is_array($current)) {
            $current = [];
        }

        $merged = array_merge($defaults, $current);
        foreach ($defaults as $email => $type) {
            $merged[$email] = $type;
        }

        $configWriter->set('inboundEmailCaseTypes', $merged);
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
            'name' => 'Rendicontazione',
            'iconClass' => 'fas fa-chart-bar',
            'color' => '#6d8f85',
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
            if (
                is_object($item)
                && ($item->type ?? null) === 'group'
                && (($item->text ?? null) === self::CONTACTS_GROUP_TEXT || ($item->name ?? null) === 'Contatti')
            ) {
                return $i + 1;
            }
        }

        foreach ($tabList as $i => $item) {
            if ($item === 'Opportunity') {
                return $i + 1;
            }
        }

        foreach ($tabList as $i => $item) {
            if ($item === 'Account') {
                return $i + 1;
            }
        }

        return count($tabList);
    }

    /**
     * Place Case (Segnalazioni) in Principali immediately before `$Rendicontazione`.
     *
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function reorderCaseBeforeReportingGroup(array $tabList): array
    {
        $without = array_values(array_filter(
            $tabList,
            static fn ($item): bool => !in_array($item, ['Case', 'Intervention', 'FoodParcelRegistration'], true)
        ));

        foreach ($without as $i => $item) {
            if (
                is_object($item)
                && ($item->type ?? null) === 'group'
                && ($item->text ?? null) === self::REPORTING_GROUP_TEXT
            ) {
                return array_merge(
                    array_slice($without, 0, $i),
                    ['Case', 'Intervention', 'FoodParcelRegistration'],
                    array_slice($without, $i)
                );
            }
        }

        foreach ($without as $i => $item) {
            // Contact STI replaced retired VolunteerEmployee as CRM-block anchor.
            if ($item === 'Contact') {
                return array_merge(
                    array_slice($without, 0, $i + 1),
                    ['Case', 'Intervention', 'FoodParcelRegistration'],
                    array_slice($without, $i + 1)
                );
            }
        }

        return array_merge($without, ['Case', 'Intervention', 'FoodParcelRegistration']);
    }

    /**
     * Enforce Knowledge Base order in the `$Support` navbar block.
     *
     * @param array<int, mixed> $tabList
     * @return array<int, mixed>
     */
    private function reorderSupportNavbarBlock(array $tabList): array
    {
        $supportEntities = self::SUPPORT_NAV_ORDER;

        $without = array_values(array_filter(
            $tabList,
            static function ($item) use ($supportEntities): bool {
                return !(is_string($item) && in_array($item, $supportEntities, true));
            }
        ));

        $insertIndex = count($without);

        foreach ($without as $i => $item) {
            if (
                is_object($item)
                && ($item->type ?? null) === 'divider'
                && ($item->text ?? null) === self::SUPPORT_DIVIDER_TEXT
            ) {
                $insertIndex = $i + 1;

                break;
            }
        }

        return array_merge(
            array_slice($without, 0, $insertIndex),
            $supportEntities,
            array_slice($without, $insertIndex)
        );
    }
}
