<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\FoodParcel\FoodParcelContactSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PHPUnit\Framework\TestCase;

class FoodParcelContactSyncTest extends TestCase
{
    private FoodParcelContactSync $sync;

    protected function setUp(): void
    {
        $this->sync = new FoodParcelContactSync($this->createMock(EntityManager::class));
    }

    public function testFormatContactPhonesFromPhoneNumberData(): void
    {
        $contact = $this->contactWith([
            'phoneNumberData' => [
                (object) ['phoneNumber' => '+39 333 1111111', 'type' => 'Mobile'],
                (object) ['phoneNumber' => '+39 02 2222222', 'type' => ''],
            ],
            'phoneNumber' => null,
        ]);

        $this->assertSame(
            "+39 333 1111111 (Mobile)\n+39 02 2222222",
            $this->sync->formatContactPhones($contact)
        );
    }

    public function testFormatContactPhonesFallsBackToPhoneNumberField(): void
    {
        $contact = $this->contactWith([
            'phoneNumberData' => [],
            'phoneNumber' => '  +39 333 9999999  ',
        ]);

        $this->assertSame('+39 333 9999999', $this->sync->formatContactPhones($contact));
    }

    public function testFormatContactPhonesPdfEscapesHtml(): void
    {
        $contact = $this->contactWith([
            'phoneNumberData' => [
                (object) ['phoneNumber' => '<script>alert(1)</script>', 'type' => 'Mobile'],
            ],
            'phoneNumber' => null,
        ]);

        $html = $this->sync->formatContactPhonesPdf($contact);

        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
    }

    public function testFormatContactPhonesPdfEmptyWhenNoPhones(): void
    {
        $contact = $this->contactWith([
            'phoneNumberData' => [],
            'phoneNumber' => null,
        ]);

        $this->assertSame('', $this->sync->formatContactPhonesPdf($contact));
    }

    public function testFormatContactAddressLineBuildsCommaSeparatedParts(): void
    {
        $contact = $this->contactWith([
            'addressStreet' => 'Via Roma 1',
            'addressCity' => 'Milano',
            'addressState' => 'MI',
            'addressPostalCode' => '20100',
            'addressCountry' => 'Italia',
        ]);

        $this->assertSame(
            'Via Roma 1, Milano, MI 20100, Italia',
            $this->sync->formatContactAddressLine($contact)
        );
    }

    public function testFormatContactAddressLineOmitsEmptyParts(): void
    {
        $contact = $this->contactWith([
            'addressStreet' => '',
            'addressCity' => 'Roma',
            'addressState' => '',
            'addressPostalCode' => '',
            'addressCountry' => '',
        ]);

        $this->assertSame('Roma', $this->sync->formatContactAddressLine($contact));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function contactWith(array $values): Entity
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('hasId')->willReturn(false);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => $values[$field] ?? null
        );

        return $contact;
    }
}
