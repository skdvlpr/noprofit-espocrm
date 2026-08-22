<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\SafehouseDefaults;
use PHPUnit\Framework\TestCase;

class SafehouseDefaultsTest extends TestCase
{
    public function testStripeSyncUserNames(): void
    {
        $this->assertSame(
            ['website', 'site_safehouse.community'],
            SafehouseDefaults::STRIPE_SYNC_USER_NAMES
        );
        $this->assertContains('website', SafehouseDefaults::STRIPE_SYNC_USER_NAMES);
    }

    public function testAssignmentNotificationEntityTypes(): void
    {
        $this->assertSame(['Task'], SafehouseDefaults::ASSIGNMENT_NOTIFICATION_ENTITY_TYPES);
    }
}
