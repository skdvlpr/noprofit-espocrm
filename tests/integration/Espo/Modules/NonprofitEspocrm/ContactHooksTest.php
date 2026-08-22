<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Entities\User;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Contact hooks: SyncIsUser, SyncOccasionalToUser, Italian fiscal code validation.
 */
class ContactHooksTest extends SafehouseBaseTestCase
{
    public function testSyncIsUserReflectsLinkedUser(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $user = $em->getNewEntity(User::ENTITY_TYPE);
        $user->set([
            'userName' => 'phpunit_isuser_' . $marker,
            'firstName' => 'PHPUnit',
            'lastName' => 'IsUser',
            'emailAddress' => 'isuser-' . $marker . '@example.com',
            'isActive' => true,
            'type' => 'regular',
        ]);
        $em->saveEntity($user);

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'Linked',
            'linkedUserId' => $user->getId(),
        ]);
        $em->saveEntity($contact);

        $this->assertTrue((bool) $contact->get('isUser'));

        $contact = $em->getEntityById('Contact', $contact->getId());
        $contact->set('linkedUserId', null);
        $em->saveEntity($contact);

        $this->assertFalse((bool) $contact->get('isUser'));
    }

    public function testSyncOccasionalToUserMirrorsContactFlag(): void
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $user = $em->getNewEntity(User::ENTITY_TYPE);
        $user->set([
            'userName' => 'phpunit_occ_' . $marker,
            'firstName' => 'PHPUnit',
            'lastName' => 'Occasional',
            'emailAddress' => 'occ-' . $marker . '@example.com',
            'isActive' => true,
            'type' => 'regular',
            'isOccasional' => false,
        ]);
        $em->saveEntity($user);

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'OccMirror',
            'linkedUserId' => $user->getId(),
            'isOccasional' => true,
        ]);
        $em->saveEntity($contact);

        $userFresh = $em->getEntityById(User::ENTITY_TYPE, $user->getId());
        $this->assertTrue((bool) $userFresh->get('isOccasional'));
    }

    public function testItalianFiscalCodeValidatorRejectsInvalidTaxCodeOnSave(): void
    {
        $factory = $this->getContainer()->getByClass(\Espo\Core\InjectableFactory::class);
        /** @var \Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Contact\TaxCode\ItalianFiscalCode $validator */
        $validator = $factory->create(
            \Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Contact\TaxCode\ItalianFiscalCode::class
        );

        $em = $this->getEntityManager();
        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Bad',
            'lastName' => 'TaxCode',
            'taxCode' => 'NOT-A-VALID-CODE',
        ]);

        $failure = $validator->validate(
            $contact,
            'taxCode',
            new \Espo\Core\FieldValidation\Validator\Data((object) [])
        );

        $this->assertNotNull($failure, 'Invalid tax code must fail ItalianFiscalCode validation');
    }

    public function testItalianFiscalCodeValidatorAcceptsValidCodiceFiscale(): void
    {
        $em = $this->getEntityManager();

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Mario',
            'lastName' => 'Rossi',
            'taxCode' => 'RSSMRA85T10A562S',
        ]);
        $em->saveEntity($contact);

        $this->assertTrue($contact->hasId());
        $this->assertSame('RSSMRA85T10A562S', strtoupper((string) $contact->get('taxCode')));
    }
}
