<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters\Associati;
use Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters\Volunteers;
use Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters\VolunteersEmployees;
use Espo\Modules\NonprofitEspocrm\Classes\Select\Contact\PrimaryFilters\VolunteersOccasionali;
use Espo\ORM\Query\SelectBuilder;
use PHPUnit\Framework\TestCase;

/**
 * Primary filters: array containment + NULL-safe occasional.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/select-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 */
class VolunteersPrimaryFilterTest extends TestCase
{
    public function testVolunteersWhereIsNullSafeAndContainsVolunteer(): void
    {
        $captured = $this->captureWhere(new Volunteers());

        $this->assertArrayHasKey('id=s', $captured);
        $this->assertSame('Volunteer', $this->subqueryValue($captured));
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
        $captured = $this->captureWhere(new VolunteersOccasionali());

        $this->assertArrayHasKey('id=s', $captured);
        $this->assertTrue($captured['isOccasional']);
        $this->assertSame('Volunteer', $this->subqueryValue($captured));
    }

    public function testAssociatiContainsMemberContact(): void
    {
        $captured = $this->captureWhere(new Associati());

        $this->assertArrayHasKey('id=s', $captured);
        $this->assertSame('MemberContact', $this->subqueryValue($captured));
    }

    public function testVolunteersEmployeesTreatsNullOccasionalAsRegular(): void
    {
        $captured = $this->captureWhere(new VolunteersEmployees());

        $this->assertArrayHasKey('OR', $captured);
        $volunteerBranch = $captured['OR'][1];
        $this->assertArrayHasKey('id=s', $volunteerBranch);
        $this->assertSame('Volunteer', $this->subqueryValue($volunteerBranch));
        $this->assertSame(
            [
                ['isOccasional' => false],
                ['isOccasional' => null],
            ],
            $volunteerBranch['OR']
        );
    }

    /**
     * @param array<string, mixed> $where
     */
    private function subqueryValue(array $where): mixed
    {
        $raw = $where['id=s'] ?? null;

        if (!is_array($raw)) {
            return null;
        }

        $clause = $raw['whereClause'] ?? [];

        if (isset($clause['value'])) {
            return $clause['value'];
        }

        if (isset($clause[0]) && is_array($clause[0]) && array_key_exists('value', $clause[0])) {
            return $clause[0]['value'];
        }

        return $clause['value'] ?? null;
    }

    private function captureWhere(object $filter): array
    {
        $captured = null;
        $queryBuilder = $this->createMock(SelectBuilder::class);
        $queryBuilder->expects($this->once())
            ->method('where')
            ->willReturnCallback(function (array $where) use (&$captured, $queryBuilder): SelectBuilder {
                $captured = $where;

                return $queryBuilder;
            });

        $filter->apply($queryBuilder);

        $this->assertIsArray($captured);

        return $captured;
    }
}
