<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Hooks\Contact\SyncRolesToLinkedUser;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRelation;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use Espo\ORM\Repository\Option\SaveOptions;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
 */
class SyncRolesToLinkedUserTest extends TestCase
{
    public function testAddsMemberRoleAndKeepsUnrelated(): void
    {
        $member = $this->role('role-member', 'Member');
        $volunteer = $this->role('role-vol', 'Volunteer');
        $other = $this->role('role-other', 'NightShift');

        $related = [];
        $unrelated = [];
        $relation = $this->createMock(RDBRelation::class);
        $relation->method('relate')->willReturnCallback(
            function (Entity $role) use (&$related): void {
                $related[] = $role->getId();
            }
        );
        $relation->method('unrelate')->willReturnCallback(
            function (Entity $role) use (&$unrelated): void {
                $unrelated[] = $role->getId();
            }
        );

        $user = $this->createMock(Entity::class);
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'type' ? 'regular' : null
        );

        $hook = new SyncRolesToLinkedUser(
            $this->entityManager($user, [$member, $volunteer], $relation, [$volunteer, $other])
        );

        $hook->afterSave(
            $this->contact(['Volunteer', 'MemberContact'], 'user-1', typeChanged: true),
            SaveOptions::fromAssoc([])
        );

        $this->assertSame(['role-member'], $related);
        $this->assertSame([], $unrelated);
    }

    public function testRemovesVolunteerWhenTypeNoLongerHasIt(): void
    {
        $volunteer = $this->role('role-vol', 'Volunteer');
        $member = $this->role('role-member', 'Member');

        $unrelated = [];
        $relation = $this->createMock(RDBRelation::class);
        $relation->expects($this->never())->method('relate');
        $relation->method('unrelate')->willReturnCallback(
            function (Entity $role) use (&$unrelated): void {
                $unrelated[] = $role->getId();
            }
        );

        $user = $this->createMock(Entity::class);
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'type' ? 'regular' : null
        );

        $hook = new SyncRolesToLinkedUser(
            $this->entityManager($user, [$volunteer, $member], $relation, [$volunteer, $member])
        );

        $hook->afterSave(
            $this->contact(['MemberContact'], 'user-1', typeChanged: true),
            SaveOptions::fromAssoc([])
        );

        $this->assertSame(['role-vol'], $unrelated);
    }

    public function testSkipsWhenNoLinkedUser(): void
    {
        $em = $this->createMock(EntityManager::class);
        $em->expects($this->never())->method('getEntityById');

        (new SyncRolesToLinkedUser($em))->afterSave(
            $this->contact(['Volunteer'], null, typeChanged: true),
            SaveOptions::fromAssoc([])
        );
    }

    /**
     * @param list<string> $types
     */
    private function contact(array $types, ?string $userId, bool $typeChanged): Entity
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('isNew')->willReturn(false);
        $entity->method('isAttributeChanged')->willReturnCallback(
            static fn (string $field): bool => $field === 'contactType' && $typeChanged
        );
        $entity->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'contactType' => $types,
                'linkedUserId' => $userId,
                default => null,
            }
        );

        return $entity;
    }

    private function role(string $id, string $name): Entity
    {
        $role = $this->createMock(Entity::class);
        $role->method('getId')->willReturn($id);
        $role->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'name' ? $name : null
        );

        return $role;
    }

    /**
     * @param list<Entity> $roles
     * @param list<Entity> $currentUserRoles
     */
    private function entityManager(
        Entity $user,
        array $roles,
        RDBRelation $relation,
        array $currentUserRoles
    ): EntityManager {
        $relation->method('find')->willReturn(new EntityCollection($currentUserRoles, 'Role'));

        $roleBuilder = $this->createMock(RDBSelectBuilder::class);
        $roleBuilder->method('find')->willReturn(new EntityCollection($roles, 'Role'));

        $roleRepo = $this->createMock(RDBRepository::class);
        $roleRepo->method('where')->willReturn($roleBuilder);

        $userRepo = $this->createMock(RDBRepository::class);
        $userRepo->method('getRelation')->with($user, 'roles')->willReturn($relation);

        $em = $this->createMock(EntityManager::class);
        $em->method('getEntityById')->with(User::ENTITY_TYPE, 'user-1')->willReturn($user);
        $em->method('getRDBRepository')->willReturnCallback(
            static function (string $type) use ($roleRepo, $userRepo): RDBRepository {
                return match ($type) {
                    'Role' => $roleRepo,
                    User::ENTITY_TYPE => $userRepo,
                    default => throw new \RuntimeException($type),
                };
            }
        );

        return $em;
    }
}
