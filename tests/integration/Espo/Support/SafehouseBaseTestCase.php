<?php

declare(strict_types=1);

namespace tests\integration\Espo\Support;

use tests\integration\Core\BaseTestCase;

/**
 * Base for Safehouse module integration tests on isolated build/test + db_test.
 */
abstract class SafehouseBaseTestCase extends BaseTestCase
{
    protected function afterStartApplication(): void
    {
        if (class_exists(\Espo\Modules\NonprofitEspocrm\Tools\Installer::class)) {
            (new \Espo\Modules\NonprofitEspocrm\Tools\Installer())->runPostInstall($this->getContainer());
        }

        if (class_exists(\Espo\Modules\WorkflowEngine\Tools\Installer::class)) {
            (new \Espo\Modules\WorkflowEngine\Tools\Installer())->runPostInstall($this->getContainer());
        }

        if (class_exists(\Espo\Modules\GoogleIntegration\Tools\Installer::class)) {
            (new \Espo\Modules\GoogleIntegration\Tools\Installer())->runPostInstall($this->getContainer());
        }

        if (class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            (new \Espo\Modules\BugTracker\Tools\Installer())->runPostInstall($this->getContainer());
        }

        $this->getConfig()->update();
        $this->getMetadata()->init(true);
    }
}
