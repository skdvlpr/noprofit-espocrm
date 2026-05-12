<?php

use Espo\Core\Container;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;

class AfterInstall
{
    private const TAB_AFTER = 'VolunteerEmployee';
    private const TAB_BEFORE_FALLBACK = 'WorkingTimeCalendar';
    private const TAB_LIST = [
        'VolunteerEmployee',
        'Member',
        'MealCount',
    ];

    /**
     * Update install-time UI config after extension files are copied.
     *
     * @param Container $container EspoCRM DI container.
     * @param array<string, mixed> $params Installer params.
     * @return void
     */
    public function run(Container $container, array $params): void
    {
        /** @var Config $config */
        $config = $container->getByClass(Config::class);

        /** @var InjectableFactory $injectableFactory */
        $injectableFactory = $container->get('injectableFactory');

        /** @var ConfigWriter $configWriter */
        $configWriter = $injectableFactory->create(ConfigWriter::class);

        $tabList = $config->get('tabList', []);
        $updatedTabList = $this->ensureTabs($tabList);

        if ($updatedTabList === $tabList) {
            return;
        }

        $configWriter->set('tabList', $updatedTabList);
        $configWriter->save();
    }

    /**
     * Ensure Safehouse custom entities are visible in the system tab list.
     *
     * @param array<int, mixed> $tabList Current tab list.
     * @return array<int, mixed>
     */
    private function ensureTabs(array $tabList): array
    {
        foreach (self::TAB_LIST as $tab) {
            if (in_array($tab, $tabList, true)) {
                continue;
            }

            $tabList = $this->insertTab($tabList, $tab);
        }

        return $tabList;
    }

    /**
     * Insert a tab near the Organization section when possible.
     *
     * @param array<int, mixed> $tabList Current tab list.
     * @param string $tab Entity type tab name.
     * @return array<int, mixed>
     */
    private function insertTab(array $tabList, string $tab): array
    {
        $afterIndex = array_search(self::TAB_AFTER, $tabList, true);

        if ($afterIndex !== false) {
            array_splice($tabList, $afterIndex + 1, 0, [$tab]);

            return $tabList;
        }

        $beforeIndex = array_search(self::TAB_BEFORE_FALLBACK, $tabList, true);

        if ($beforeIndex !== false) {
            array_splice($tabList, $beforeIndex, 0, [$tab]);

            return $tabList;
        }

        $tabList[] = $tab;

        return $tabList;
    }
}
