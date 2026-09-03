<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\ApplicationUser;
use Espo\Core\FieldSanitize\SanitizeManager;
use Espo\Core\HookManager;
use Espo\Core\InjectableFactory;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Hooks\Contact\ProtectLinkedUser;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Contact hooks: SyncIsUser, SyncOccasionalToUser, ProtectLinkedUser, Italian fiscal code.
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
        $this->assertSame('RSSMRA85T10A562S', $contact->get('taxCode'));
    }

    public function testContactTaxCodeSanitizerUppercasesLowercaseInput(): void
    {
        $data = (object) [
            'taxCode' => 'rssmra85t10a562s',
        ];

        $manager = $this->getInjectableFactory()->create(SanitizeManager::class);
        $manager->process('Contact', $data);

        $this->assertSame('RSSMRA85T10A562S', $data->taxCode);
    }

    public function testProtectLinkedUserBlocksRegularActorBindingAnotherUser(): void
    {
        $volunteer = $this->createRegularUser('bindvol');
        $victim = $this->createRegularUser('bindvictim');

        $hook = $this->protectLinkedUserHook($volunteer);
        $contact = $this->getEntityManager()->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Bind',
            'lastName' => 'Attack',
            'linkedUserId' => $victim->getId(),
            'assignedUserId' => $volunteer->getId(),
        ]);

        $this->assertForbidden(
            fn () => $hook->beforeSave($contact, SaveOptions::fromAssoc([]))
        );
    }

    public function testProtectLinkedUserAllowsSelfLinkAndUnlink(): void
    {
        $volunteer = $this->createRegularUser('selfvol');
        $hook = $this->protectLinkedUserHook($volunteer);

        $self = $this->getEntityManager()->getNewEntity('Contact');
        $self->set([
            'firstName' => 'Self',
            'lastName' => 'Link',
            'linkedUserId' => $volunteer->getId(),
        ]);
        $hook->beforeSave($self, SaveOptions::fromAssoc([]));

        $unlink = $this->getEntityManager()->getNewEntity('Contact');
        $unlink->set([
            'firstName' => 'Un',
            'lastName' => 'Link',
        ]);
        $hook->beforeSave($unlink, SaveOptions::fromAssoc([]));

        $this->addToAssertionCount(1);
    }

    public function testProtectLinkedUserBlocksRegularActorBindingPortalUser(): void
    {
        $volunteer = $this->createRegularUser('portalvol');
        $portal = $this->createUser([
            'userName' => 'phpunit_portal_' . $this->uniqueMarker(),
            'firstName' => 'PHPUnit',
            'lastName' => 'Portal',
            'type' => 'portal',
            'isActive' => true,
        ], null, true);

        $hook = $this->protectLinkedUserHook($volunteer);
        $contact = $this->getEntityManager()->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Portal',
            'lastName' => 'Bind',
            'portalUserId' => $portal->getId(),
        ]);

        $this->assertForbidden(
            fn () => $hook->beforeSave($contact, SaveOptions::fromAssoc([]))
        );
    }

    public function testProtectLinkedUserSkipAllStillAllowsForeignBind(): void
    {
        $volunteer = $this->createRegularUser('skipvol');
        $victim = $this->createRegularUser('skipvictim');
        $hook = $this->protectLinkedUserHook($volunteer);

        $contact = $this->getEntityManager()->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Skip',
            'lastName' => 'All',
            'linkedUserId' => $victim->getId(),
        ]);
        $hook->beforeSave($contact, SaveOptions::fromAssoc([SaveOption::SKIP_ALL => true]));

        $this->addToAssertionCount(1);
    }

    public function testProtectLinkedUserSaveAsRegularUserRejectsForeignBind(): void
    {
        $volunteer = $this->createRegularUser('savevol');
        $victim = $this->createRegularUser('savevictim');

        $this->getContainer()->getByClass(ApplicationUser::class)->setUser($volunteer);
        $this->resetHookInstances();

        $em = $this->getEntityManager();
        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Save',
            'lastName' => 'Attack',
            'linkedUserId' => $victim->getId(),
            'assignedUserId' => $volunteer->getId(),
        ]);

        $this->assertForbidden(fn () => $em->saveEntity($contact));
    }

    private function createRegularUser(string $prefix): User
    {
        return $this->createUser([
            'userName' => 'phpunit_' . $prefix . '_' . $this->uniqueMarker(),
            'firstName' => 'PHPUnit',
            'lastName' => ucfirst($prefix),
            'type' => 'regular',
            'isActive' => true,
        ]);
    }

    private function protectLinkedUserHook(User $user): ProtectLinkedUser
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);

        return $factory->createWith(ProtectLinkedUser::class, [
            'user' => $user,
        ]);
    }

    private function resetHookInstances(): void
    {
        $hookManager = $this->getContainer()->getByClass(HookManager::class);
        $prop = new \ReflectionProperty($hookManager, 'hooks');
        $prop->setValue($hookManager, []);
    }
}
