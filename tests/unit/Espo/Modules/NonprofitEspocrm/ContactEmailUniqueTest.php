<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Entities\EmailAddress;
use Espo\Modules\Crm\Entities\Contact as ContactEntity;
use Espo\Modules\NonprofitEspocrm\Classes\DuplicateWhereBuilders\Contact as ContactDuplicateWhereBuilder;
use Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Contact\EmailAddress\UniqueAmongContacts;
use Espo\Modules\NonprofitEspocrm\Tools\ContactEmailUniqueness;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class ContactEmailUniqueTest extends TestCase
{
    public function testCollectAddressesNormalizesAndDedupes(): void
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'emailAddress' => '  Foo@Example.COM ',
                'emailAddressData' => [
                    ['emailAddress' => 'foo@example.com'],
                    ['emailAddress' => 'bar@example.com'],
                ],
                default => null,
            }
        );

        $tool = new ContactEmailUniqueness($this->createMock(EntityManager::class));

        $this->assertSame(
            ['foo@example.com', 'bar@example.com'],
            $tool->collectAddresses($contact)
        );
    }

    public function testHasConflictWhenAnotherContactOwnsAddress(): void
    {
        $contact = $this->contactEntity('dup@example.com', 'contact-self');
        $tool = new ContactEmailUniqueness($this->entityManagerWithOwner('contact-other'));

        $this->assertTrue($tool->hasConflict($contact));
        $this->assertInstanceOf(
            Failure::class,
            (new UniqueAmongContacts($tool))->validate(
                $contact,
                'emailAddress',
                new Data(new stdClass())
            )
        );
    }

    public function testSameContactIdIsNotConflict(): void
    {
        $contact = $this->contactEntity('mine@example.com', 'contact-self');
        $tool = new ContactEmailUniqueness($this->entityManagerWithOwner('contact-self'));

        $this->assertFalse($tool->hasConflict($contact));
        $this->assertNull(
            (new UniqueAmongContacts($tool))->validate(
                $contact,
                'emailAddress',
                new Data(new stdClass())
            )
        );
    }

    public function testUserOwningAddressIsNotContactConflict(): void
    {
        $contact = $this->contactEntity('shared@example.com', 'contact-new');
        $tool = new ContactEmailUniqueness($this->entityManagerWithNoContactOwner());

        $this->assertFalse($tool->hasConflict($contact));
    }

    public function testDuplicateWhereBuilderUsesCollectedAddresses(): void
    {
        $contact = $this->contactEntity('dup@example.com', 'contact-self');
        $builder = new ContactDuplicateWhereBuilder(
            new ContactEmailUniqueness($this->createMock(EntityManager::class))
        );

        $this->assertNotNull($builder->build($contact));
    }

    private function contactEntity(string $email, string $id): Entity
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('hasId')->willReturn($id !== '');
        $contact->method('getId')->willReturn($id);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'emailAddress' => $email,
                'emailAddressData' => null,
                default => null,
            }
        );

        return $contact;
    }

    private function entityManagerWithOwner(string $ownerContactId): EntityManager
    {
        $email = $this->createMock(Entity::class);
        $email->method('getId')->willReturn('ea-1');

        $link = $this->createMock(Entity::class);
        $link->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'entityId' => $ownerContactId,
                default => null,
            }
        );

        $existing = $this->createMock(Entity::class);

        $eaBuilder = $this->createMock(RDBSelectBuilder::class);
        $eaBuilder->method('findOne')->willReturn($email);

        $eaRepo = $this->createMock(RDBRepository::class);
        $eaRepo->method('where')->willReturn($eaBuilder);

        $linkBuilder = $this->createMock(RDBSelectBuilder::class);
        $linkBuilder->method('find')->willReturn(
            new EntityCollection([$link], EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS)
        );

        $linkRepo = $this->createMock(RDBRepository::class);
        $linkRepo->method('where')->willReturn($linkBuilder);

        $contactBuilder = $this->createMock(RDBSelectBuilder::class);
        $contactBuilder->method('where')->willReturnSelf();
        $contactBuilder->method('findOne')->willReturn($existing);

        $contactRepo = $this->createMock(RDBRepository::class);
        $contactRepo->method('select')->willReturn($contactBuilder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->willReturnCallback(
            static function (string $type) use ($eaRepo, $linkRepo, $contactRepo): RDBRepository {
                return match ($type) {
                    EmailAddress::ENTITY_TYPE => $eaRepo,
                    EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS => $linkRepo,
                    ContactEntity::ENTITY_TYPE => $contactRepo,
                    default => throw new \RuntimeException($type),
                };
            }
        );

        return $em;
    }

    private function entityManagerWithNoContactOwner(): EntityManager
    {
        $email = $this->createMock(Entity::class);
        $email->method('getId')->willReturn('ea-2');

        $link = $this->createMock(Entity::class);
        $link->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'entityId' => 'user-1',
                default => null,
            }
        );

        $eaBuilder = $this->createMock(RDBSelectBuilder::class);
        $eaBuilder->method('findOne')->willReturn($email);

        $eaRepo = $this->createMock(RDBRepository::class);
        $eaRepo->method('where')->willReturn($eaBuilder);

        $linkBuilder = $this->createMock(RDBSelectBuilder::class);
        $linkBuilder->method('find')->willReturn(
            new EntityCollection([$link], EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS)
        );

        $linkRepo = $this->createMock(RDBRepository::class);
        $linkRepo->method('where')->willReturn($linkBuilder);

        $contactBuilder = $this->createMock(RDBSelectBuilder::class);
        $contactBuilder->method('where')->willReturnSelf();
        $contactBuilder->method('findOne')->willReturn(null);

        $contactRepo = $this->createMock(RDBRepository::class);
        $contactRepo->method('select')->willReturn($contactBuilder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->willReturnCallback(
            static function (string $type) use ($eaRepo, $linkRepo, $contactRepo): RDBRepository {
                return match ($type) {
                    EmailAddress::ENTITY_TYPE => $eaRepo,
                    EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS => $linkRepo,
                    ContactEntity::ENTITY_TYPE => $contactRepo,
                    default => throw new \RuntimeException($type),
                };
            }
        );

        return $em;
    }
}
