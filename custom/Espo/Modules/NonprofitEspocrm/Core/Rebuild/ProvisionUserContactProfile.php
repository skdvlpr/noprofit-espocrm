<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\InjectableFactory;
use Espo\Core\Rebuild\RebuildAction;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Config\ConfigWriter;
use Espo\Core\Utils\File\Manager as FileManager;
use Espo\Core\DataManager;

/**
 * Ensure Custom User detail layout includes Volunteer/Member profile panels.
 * Custom layouts override module layouts, so we must write Custom on rebuild.
 *
 * @noinspection PhpUnused
 */
class ProvisionUserContactProfile implements RebuildAction
{
    /** Bump when User Volunteer/Member Custom layout must be rewritten on prod rebuild. */
    private const PROVISION_VERSION = '2026-08-03-user-contact-profile-v3';
    private const CONFIG_KEY = 'safehouseUserContactProfileVersion';

    private const LAYOUT_PATH = 'custom/Espo/Custom/Resources/layouts/User/detail.json';
    private const MODULE_LAYOUT_PATH =
        'custom/Espo/Modules/NonprofitEspocrm/Resources/layouts/User/detail.json';

    public function __construct(
        private Config $config,
        private InjectableFactory $injectableFactory,
        private FileManager $fileManager,
        private DataManager $dataManager,
        private \Espo\Core\Container $container
    ) {}

    public function process(): void
    {
        $this->refreshContactsNavbar();

        if ($this->config->get(self::CONFIG_KEY) === self::PROVISION_VERSION) {
            if ($this->layoutIsReady()) {
                return;
            }
        }

        $this->writeUserDetailLayout();

        if (!$this->layoutIsReady()) {
            return;
        }

        $configWriter = $this->injectableFactory->create(ConfigWriter::class);
        $configWriter->set(self::CONFIG_KEY, self::PROVISION_VERSION);
        $configWriter->save();
    }

    private function refreshContactsNavbar(): void
    {
        try {
            (new \Espo\Modules\NonprofitEspocrm\Tools\Installer())
                ->refreshContactsNavbar($this->container);
        } catch (\Throwable $e) {
            // Avoid failing full rebuild if tab reorder has a transient issue.
        }
    }

    private function writeUserDetailLayout(): void
    {
        if (!$this->fileManager->isFile(self::MODULE_LAYOUT_PATH)) {
            return;
        }

        $contents = $this->fileManager->getContents(self::MODULE_LAYOUT_PATH);
        $this->fileManager->putContents(self::LAYOUT_PATH, $contents);
        $this->dataManager->updateCacheTimestamp();
    }

    private function layoutIsReady(): bool
    {
        if (!$this->fileManager->isFile(self::LAYOUT_PATH)) {
            return false;
        }

        $raw = $this->fileManager->getContents(self::LAYOUT_PATH);

        return str_contains($raw, 'volunteeringProfile')
            && str_contains($raw, 'memberProfile')
            && str_contains($raw, 'activityCompetences')
            && str_contains($raw, 'isOccasional')
            && str_contains($raw, 'birthProvince')
            && str_contains($raw, 'positionsHeld')
            && str_contains($raw, 'memberNotes');
    }
}
