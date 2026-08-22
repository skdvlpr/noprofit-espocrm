<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\StatusGuard;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

class StatusGuardTest extends TestCase
{
    public function testSkipOptionConstant(): void
    {
        $this->assertSame('safehouseSkipStatusGuard', StatusGuard::SKIP_OPTION);
    }

    public function testCannotBeInstantiated(): void
    {
        $reflection = new ReflectionClass(StatusGuard::class);

        $this->assertTrue($reflection->getConstructor()->isPrivate());
    }
}
