<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\BugTracker;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * BugTracker module metadata (converted from bin/smoke-bug-tracker.php core checks).
 */
class BugTrackerTest extends SafehouseBaseTestCase
{
    public function testBugReportScopeAndSettingsFields(): void
    {
        if (!class_exists(\Espo\Modules\BugTracker\Tools\Installer::class)) {
            $this->markTestSkipped('BugTracker module not installed.');
        }

        $metadata = $this->getMetadata();

        $this->assertSame('BugTracker', $metadata->get(['scopes', 'BugReport', 'module']));
        $this->assertTrue((bool) $metadata->get(['scopes', 'BugReport', 'entity']));
        $this->assertSame(
            'attachmentMultiple',
            $metadata->get(['entityDefs', 'BugReport', 'fields', 'screenshots', 'type'])
        );
        $this->assertTrue(
            (bool) $metadata->get(['entityDefs', 'Settings', 'fields', 'bugTrackerEnabled'])
        );
        $this->assertTrue(
            (bool) $metadata->get(['entityDefs', 'Settings', 'fields', 'bugTrackerTechnicianEmail'])
        );
    }
}
