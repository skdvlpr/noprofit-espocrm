<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\SafehouseAuroraThemes;

use Espo\Modules\SafehouseAuroraThemes\Tools\Installer;
use integration\Core\NoTransaction;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Safehouse Aurora themes metadata after post-install rebuild.
 */
#[NoTransaction]
class SafehouseAuroraThemesTest extends SafehouseBaseTestCase
{
    public function testPostInstallRegistersAuroraThemesInMetadata(): void
    {
        if (!class_exists(Installer::class)) {
            $this->markTestSkipped('SafehouseAuroraThemes module not installed.');
        }

        (new Installer())->runPostInstall($this->getContainer());
        $this->getMetadata()->init(true);

        $themes = $this->getMetadata()->get('themes') ?? [];

        $this->assertArrayHasKey('SafehouseAurora', $themes);
        $this->assertArrayHasKey('SafehouseAuroraLight', $themes);
        $this->assertSame(
            'client/custom/css/safehouse-aurora/safehouse-aurora.css',
            $themes['SafehouseAurora']['stylesheet'] ?? null
        );
        $this->assertSame(
            'client/custom/css/safehouse-aurora/safehouse-aurora-light.css',
            $themes['SafehouseAuroraLight']['stylesheet'] ?? null
        );
        $this->assertTrue((bool) ($themes['SafehouseAurora']['isDark'] ?? false));
        $this->assertFalse((bool) ($themes['SafehouseAuroraLight']['isDark'] ?? true));
    }
}
