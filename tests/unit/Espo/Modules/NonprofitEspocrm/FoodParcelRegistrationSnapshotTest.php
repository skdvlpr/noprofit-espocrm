<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelContactSync;
use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelRegistrationSnapshot;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

class FoodParcelRegistrationSnapshotTest extends TestCase
{
    public function testCollectReturnsSnapshotAttributes(): void
    {
        $registration = $this->registrationWith([
            'taxCode' => 'RSSMRA85T10A562S',
            'phone' => '+39 333',
            'notesPdf' => '<p>note</p>',
            'entryDatesText' => "01.01.2026\n02.01.2026",
        ]);

        $snapshot = new FoodParcelRegistrationSnapshot($this->createMock(FoodParcelContactSync::class));
        $state = $snapshot->collect($registration);

        $this->assertSame('RSSMRA85T10A562S', $state['taxCode']);
        $this->assertSame('+39 333', $state['phone']);
        $this->assertSame('<p>note</p>', $state['notesPdf']);
        $this->assertSame("01.01.2026\n02.01.2026", $state['entryDatesText']);
        $this->assertArrayHasKey('exitDatesText', $state);
    }

    public function testHasChangesDetectsDifference(): void
    {
        $registration = $this->registrationWith(['taxCode' => 'NEW']);
        $before = ['taxCode' => 'OLD'];

        $snapshot = new FoodParcelRegistrationSnapshot($this->createMock(FoodParcelContactSync::class));

        $this->assertTrue($snapshot->hasChanges($registration, $before));
    }

    public function testHasChangesFalseWhenUnchanged(): void
    {
        $registration = $this->registrationWith([
            'taxCode' => 'SAME',
            'phone' => null,
            'notesPdf' => '',
        ]);
        $before = [
            'taxCode' => 'SAME',
            'phone' => null,
            'notesPdf' => '',
        ];

        $snapshot = new FoodParcelRegistrationSnapshot($this->createMock(FoodParcelContactSync::class));

        $this->assertFalse($snapshot->hasChanges($registration, $before));
    }

    public function testApplySyncsContactAndFormatsDerivedFields(): void
    {
        $contactSync = $this->createMock(FoodParcelContactSync::class);
        $contactSync->expects($this->once())->method('syncFromContactId');

        $registration = $this->createMock(Entity::class);
        $registration->method('get')->willReturnMap([
            ['notes', "Line one\n<b>bold</b>"],
            ['entryDates', ['2026-03-01', '2026-01-15']],
            ['exitDates', null],
        ]);
        $registration->expects($this->once())->method('set')->with([
            'notesPdf' => '<div style="margin:0;padding:0;line-height:1.35;">Line one</div>'
                . '<div style="margin:0;padding:0;line-height:1.35;">bold</div>',
            'entryDatesText' => "01.03.2026\n15.01.2026",
            'exitDatesText' => '',
        ]);

        $snapshot = new FoodParcelRegistrationSnapshot($contactSync);
        $snapshot->apply($registration);
    }

    /**
     * @param array<string, mixed> $values
     */
    private function registrationWith(array $values): Entity
    {
        $registration = $this->createMock(Entity::class);
        $registration->method('get')->willReturnCallback(
            static fn (string $field): mixed => $values[$field] ?? null
        );

        return $registration;
    }
}
