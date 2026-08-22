<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingProfileRegistry;
use PHPUnit\Framework\TestCase;

class ReportingProfileRegistryTest extends TestCase
{
    private ReportingProfileRegistry $registry;

    protected function setUp(): void
    {
        $this->registry = new ReportingProfileRegistry();
    }

    public function testGetProfileForMealCount(): void
    {
        $profile = $this->registry->getProfile('MealCount');

        $this->assertNotNull($profile);
        $this->assertSame('MealCount', $profile->entityType);
        $this->assertSame('date', $profile->dateAttribute);
        $this->assertSame(['adults', 'minors', 'totalMeals', 'foodCost'], $profile->sumAttributes);
    }

    public function testGetProfileForPrimaNota(): void
    {
        $profile = $this->registry->getProfile('PrimaNota');

        $this->assertNotNull($profile);
        $this->assertSame('transactionDate', $profile->dateAttribute);
        $this->assertSame(['amountIn', 'amountOut'], $profile->sumAttributes);
    }

    public function testGetProfileUnknownReturnsNull(): void
    {
        $this->assertNull($this->registry->getProfile('Contact'));
    }

    public function testIsReportingEntity(): void
    {
        $this->assertTrue($this->registry->isReportingEntity('AssociationMealCount'));
        $this->assertFalse($this->registry->isReportingEntity('Account'));
    }

    public function testGetReportingEntityTypes(): void
    {
        $this->assertSame(
            ['MealCount', 'AssociationMealCount', 'PrimaNota'],
            $this->registry->getReportingEntityTypes()
        );
    }
}
