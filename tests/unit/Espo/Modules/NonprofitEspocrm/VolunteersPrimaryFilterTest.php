<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters\Volunteers;
use Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters\VolunteersEmployees;
use Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters\VolunteersOccasionali;
use Espo\ORM\Query\SelectBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Primary filters: regular volunteers include false OR null isOccasional.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/select-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 */
class VolunteersPrimaryFilterTest extends TestCase
{
    public function testVolunteersWhereIsNullSafe(): void
    {
        $captured = null;
        $queryBuilder = $this->createMock(SelectBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnCallback(function (array $where) use (&$captured, $queryBuilder): SelectBuilder {
                $captured = $where;

                return $queryBuilder;
            });

        (new Volunteers())->apply($queryBuilder);

        $this->assertSame('Volunteer', $captured['contactType']);
        $this->assertSame(
            [
                ['isOccasional' => false],
                ['isOccasional' => null],
            ],
            $captured['OR']
        );
        $this->assertArrayNotHasKey('isOccasional!=', $captured);
    }

    public function testOccasionaliStillRequiresTrue(): void
    {
        $captured = null;
        $queryBuilder = $this->createMock(SelectBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnCallback(function (array $where) use (&$captured, $queryBuilder): SelectBuilder {
                $captured = $where;

                return $queryBuilder;
            });

        (new VolunteersOccasionali())->apply($queryBuilder);

        $this->assertSame('Volunteer', $captured['contactType']);
        $this->assertTrue($captured['isOccasional']);
    }

    public function testVolunteersEmployeesTreatsNullOccasionalAsRegular(): void
    {
        $captured = null;
        $queryBuilder = $this->createMock(SelectBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnCallback(function (array $where) use (&$captured, $queryBuilder): SelectBuilder {
                $captured = $where;

                return $queryBuilder;
            });

        (new VolunteersEmployees())->apply($queryBuilder);

        $this->assertArrayHasKey('OR', $captured);
        $volunteerBranch = $captured['OR'][1];
        $this->assertSame('Volunteer', $volunteerBranch['contactType']);
        $this->assertSame(
            [
                ['isOccasional' => false],
                ['isOccasional' => null],
            ],
            $volunteerBranch['OR']
        );
    }
}
