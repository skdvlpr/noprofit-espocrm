<?php

declare(strict_types=1);

namespace tests\integration\Espo\Core;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Core compatibility on isolated test instance (db_test, build/test).
 */
class CoreCompatibilityTest extends SafehouseBaseTestCase
{
    private const EXPECTED_MODULES = [
        'NonprofitEspocrm',
        'GoogleIntegration',
        'WorkflowEngine',
        'SafehouseAuroraThemes',
        'BugTracker',
    ];

    public function testApplicationBootstrapsOnTestInstance(): void
    {
        $this->assertNotNull($this->getContainer());
        $this->assertSame('db_test', $this->getConfig()->get('database.dbname'));
    }

    public function testCustomModulesAreRegistered(): void
    {
        foreach (self::EXPECTED_MODULES as $module) {
            $this->assertTrue(
                class_exists("Espo\\Modules\\{$module}\\AfterInstall")
                    || class_exists("Espo\\Modules\\{$module}\\Tools\\Installer"),
                "Module {$module} must expose AfterInstall or Tools\\Installer"
            );
        }
    }
}
