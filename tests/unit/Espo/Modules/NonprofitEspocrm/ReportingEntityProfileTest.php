<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Reporting\ReportingEntityProfile;
use PHPUnit\Framework\TestCase;

class ReportingEntityProfileTest extends TestCase
{
    public function testExportTotalAttributesDefaultToSumAttributes(): void
    {
        $profile = new ReportingEntityProfile(
            'MealCount',
            'date',
            ['adults', 'totalMeals'],
        );

        $this->assertSame(['adults', 'totalMeals'], $profile->exportTotalAttributes);
        $this->assertSame('name', $profile->totalsLabelAttribute);
    }

    public function testExportTotalAttributesCanBeOverridden(): void
    {
        $profile = new ReportingEntityProfile(
            'MealCount',
            'date',
            ['adults', 'minors', 'totalMeals'],
            ['totalMeals'],
            'title',
        );

        $this->assertSame(['totalMeals'], $profile->exportTotalAttributes);
        $this->assertSame('title', $profile->totalsLabelAttribute);
    }
}
