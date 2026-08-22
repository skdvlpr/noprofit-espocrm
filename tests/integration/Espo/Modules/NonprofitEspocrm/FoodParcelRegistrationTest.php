<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * FoodParcelRegistration sync hooks (converted from bin/smoke-food-parcel.php).
 */
class FoodParcelRegistrationTest extends SafehouseBaseTestCase
{
    public function testRegistrationSyncsContactSnapshotAndDerivedTextFields(): void
    {
        $em = $this->getEntityManager();

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'FoodParcel' . bin2hex(random_bytes(2)),
            'taxCode' => 'PLLLRT80A01H501Z',
            'phoneNumber' => '+39 055 1234567',
            'phoneNumberData' => [
                (object) [
                    'phoneNumber' => '+39 055 1234567',
                    'type' => 'Mobile',
                    'primary' => true,
                    'optOut' => false,
                    'invalid' => false,
                ],
                (object) [
                    'phoneNumber' => '+39 055 7654321',
                    'type' => 'Mobile',
                    'primary' => false,
                    'optOut' => false,
                    'invalid' => false,
                ],
            ],
            'addressStreet' => 'Via PHPUnit 12',
            'addressCity' => 'Firenze',
            'addressPostalCode' => '50100',
            'addressCountry' => 'Italy',
        ]);
        $em->saveEntity($contact);

        $today = date('Y-m-d');
        $registration = $em->getNewEntity('FoodParcelRegistration');
        $registration->set([
            'contactId' => $contact->getId(),
            'household' => 3,
            'notes' => 'PHPUnit food parcel<br />second line',
            'entryDates' => [$today],
            'exitDates' => [$today],
        ]);
        $em->saveEntity($registration);

        $saved = $em->getEntityById('FoodParcelRegistration', $registration->getId());
        $this->assertNotNull($saved);
        $this->assertSame('PLLLRT80A01H501Z', $saved->get('taxCode'));

        $phoneText = (string) $saved->get('phone');
        $this->assertStringContainsString('+39 055 1234567', $phoneText);
        $this->assertStringContainsString('+39 055 7654321', $phoneText);
        $this->assertSame('Via PHPUnit 12', $saved->get('addressStreet'));
        $this->assertStringContainsString('Via PHPUnit 12', (string) $saved->get('addressLine'));
        $this->assertStringContainsString('+39 055 7654321', (string) $saved->get('phonePdf'));

        $notesPdf = (string) $saved->get('notesPdf');
        $this->assertStringNotContainsString('<br />', $notesPdf);
        $this->assertStringContainsString('PHPUnit food parcel', $notesPdf);
        $this->assertStringContainsString('second line', $notesPdf);
        $this->assertStringContainsString('line-height:1.35', $notesPdf);

        $displayToday = date('d.m.Y', strtotime($today));
        $this->assertStringContainsString($displayToday, (string) $saved->get('entryDatesText'));
        $this->assertStringContainsString($displayToday, (string) $saved->get('exitDatesText'));

        $contact->set([
            'phoneNumberData' => [
                (object) [
                    'phoneNumber' => '+39 055 9999999',
                    'type' => 'Office',
                    'primary' => true,
                    'optOut' => false,
                    'invalid' => false,
                ],
            ],
            'addressCity' => 'Prato',
        ]);
        $em->saveEntity($contact);

        $updated = $em->getEntityById('FoodParcelRegistration', $registration->getId());
        $this->assertStringContainsString('+39 055 9999999', (string) $updated->get('phone'));
        $this->assertSame('Prato', $updated->get('addressCity'));
    }
}
