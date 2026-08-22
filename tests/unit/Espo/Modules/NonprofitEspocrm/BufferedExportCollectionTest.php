<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Export\Support\BufferedExportCollection;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

class BufferedExportCollectionTest extends TestCase
{
    public function testIteratorYieldsAllEntitiesThenTotals(): void
    {
        $entity1 = $this->entityWithId('1');
        $entity2 = $this->entityWithId('2');
        $totals = $this->entityWithId('totals');

        $collection = new BufferedExportCollection([$entity1, $entity2], $totals);

        $this->assertSame([$entity1, $entity2, $totals], iterator_to_array($collection));
    }

    public function testIteratorWithoutTotalsEntity(): void
    {
        $entity = $this->entityWithId('only');

        $collection = new BufferedExportCollection([$entity], null);

        $this->assertSame([$entity], iterator_to_array($collection));
    }

    public function testEmptyCollection(): void
    {
        $collection = new BufferedExportCollection([], null);

        $this->assertSame([], iterator_to_array($collection));
    }

    private function entityWithId(string $id): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('getId')->willReturn($id);

        return $entity;
    }
}
