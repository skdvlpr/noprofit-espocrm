<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\UserContactProfileSync;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class UserContactProfileSyncTest extends TestCase
{
    public function testMayAutoCreateContactFalseForVolunteerAndEmployee(): void
    {
        $this->assertFalse(UserContactProfileSync::mayAutoCreateContact(true, false, false));
        $this->assertFalse(UserContactProfileSync::mayAutoCreateContact(false, true, false));
        $this->assertFalse(UserContactProfileSync::mayAutoCreateContact(true, true, true));
    }

    public function testMayAutoCreateContactTrueForMemberOnly(): void
    {
        $this->assertTrue(UserContactProfileSync::mayAutoCreateContact(false, false, true));
        $this->assertFalse(UserContactProfileSync::mayAutoCreateContact(false, false, false));
    }

    public function testSourceContactIdLinksWithoutCreatingContact(): void
    {
        $saved = [];
        $contact = $this->createMock(Entity::class);
        $contact->method('set')->willReturnCallback(
            function (string $field, mixed $value) use ($contact): Entity {
                $this->assertSame('linkedUserId', $field);
                $this->assertSame('user-1', $value);

                return $contact;
            }
        );

        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('getNewEntity');
        $em->method('getEntityById')->with('Contact', 'contact-1')->willReturn($contact);
        $em->expects($this->once())->method('saveEntity')->willReturnCallback(
            function (Entity $entity) use (&$saved, $contact): void {
                $this->assertSame($contact, $entity);
                $saved[] = $entity;
            }
        );

        $user = $this->createMock(Entity::class);
        $user->method('getId')->willReturn('user-1');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'type' => 'regular',
                'sourceContactId' => 'contact-1',
                'rolesNames' => ['id1' => 'Regular'],
                default => null,
            }
        );

        $sync = new UserContactProfileSync($em);
        $sync->syncFromUser($user);

        $this->assertCount(1, $saved);
    }

    public function testVolunteerWithoutContactDoesNotAutoCreate(): void
    {
        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->with('Contact')->willReturn($this->emptyContactRepo());
        $em->expects($this->never())->method('getNewEntity');
        $em->expects($this->never())->method('saveEntity');

        $user = $this->createMock(Entity::class);
        $user->method('getId')->willReturn('user-2');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'type' => 'regular',
                'sourceContactId' => '',
                'rolesNames' => ['id1' => 'Volunteer'],
                default => null,
            }
        );

        $sync = new UserContactProfileSync($em);
        $sync->syncFromUser($user);
    }

    public function testLinkFromSourceContactDoesNotSetAssignedUserId(): void
    {
        $setFields = [];
        $contact = $this->createMock(Entity::class);
        $contact->method('set')->willReturnCallback(
            function (string $field, mixed $value) use (&$setFields, $contact): Entity {
                $setFields[$field] = $value;

                return $contact;
            }
        );

        $em = $this->createMock(EntityManager::class);
        $em->method('getEntityById')->willReturn($contact);
        $em->expects($this->once())->method('saveEntity');

        $user = $this->createMock(Entity::class);
        $user->method('getId')->willReturn('user-3');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'sourceContactId' ? 'contact-3' : null
        );

        $sync = new UserContactProfileSync($em);
        $this->assertTrue($sync->linkFromSourceContact($user));
        $this->assertSame('user-3', $setFields['linkedUserId'] ?? null);
        $this->assertArrayNotHasKey('assignedUserId', $setFields);
    }

    public function testSyncFromUserDoesNotCopyCompetencesOntoContact(): void
    {
        $setFields = [];
        $contact = $this->createMock(Entity::class);
        $contact->method('getId')->willReturn('contact-vol');
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'contactType' ? 'Volunteer' : null
        );
        $contact->method('set')->willReturnCallback(
            function (string $field, mixed $value) use (&$setFields, $contact): Entity {
                $setFields[$field] = $value;

                return $contact;
            }
        );

        $builder = $this->createMock(RDBSelectBuilder::class);
        $builder->method('find')->willReturn(new EntityCollection([$contact], 'Contact'));

        $repo = $this->createMock(RDBRepository::class);
        $repo->method('where')->willReturn($builder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->with('Contact')->willReturn($repo);
        $em->expects($this->once())->method('saveEntity')->with($contact, $this->anything());

        $user = $this->createMock(Entity::class);
        $user->method('getId')->willReturn('user-vol');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'type' => 'regular',
                'sourceContactId' => '',
                'rolesNames' => ['id1' => 'Volunteer'],
                'activityCompetences' => ['Reception', 'Cleaning'],
                'isOccasional' => true,
                'weeklyHours' => 8,
                default => null,
            }
        );

        $sync = new UserContactProfileSync($em);
        $sync->syncFromUser($user);

        $this->assertArrayNotHasKey('activityCompetences', $setFields);
        $this->assertArrayNotHasKey('isOccasional', $setFields);
        $this->assertArrayNotHasKey('weeklyHours', $setFields);
    }

    public function testLoadFromContactSetsUserReflectionFields(): void
    {
        $setFields = [];
        $contact = $this->createMock(Entity::class);
        $contact->method('getId')->willReturn('contact-4');
        $contact->method('hasAttribute')->willReturn(true);
        $contact->method('has')->willReturn(true);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'activityCompetences' => ['Reception'],
                'isOccasional' => true,
                'name' => 'Ada Volunteer',
                'firstName' => 'Ada',
                'lastName' => 'Volunteer',
                default => null,
            }
        );

        $builder = $this->createMock(RDBSelectBuilder::class);
        $builder->method('find')->willReturn(new EntityCollection([$contact], 'Contact'));

        $repo = $this->createMock(RDBRepository::class);
        $repo->method('where')->willReturn($builder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->with('Contact')->willReturn($repo);

        $user = $this->createMock(Entity::class);
        $user->method('getId')->willReturn('user-4');
        $user->method('set')->willReturnCallback(
            function (string $field, mixed $value) use (&$setFields, $user): Entity {
                $setFields[$field] = $value;

                return $user;
            }
        );

        $sync = new UserContactProfileSync($em);
        $sync->loadFromContact($user);

        $this->assertSame(['Reception'], $setFields['activityCompetences'] ?? null);
        $this->assertTrue((bool) ($setFields['isOccasional'] ?? false));
        $this->assertSame('contact-4', $setFields['linkedContactId'] ?? null);
        $this->assertSame('Ada Volunteer', $setFields['linkedContactName'] ?? null);
    }

    private function emptyContactRepo(): RDBRepository
    {
        $builder = $this->createMock(RDBSelectBuilder::class);
        $builder->method('find')->willReturn(new EntityCollection([], 'Contact'));

        $repo = $this->createMock(RDBRepository::class);
        $repo->method('where')->willReturn($builder);

        return $repo;
    }
}
