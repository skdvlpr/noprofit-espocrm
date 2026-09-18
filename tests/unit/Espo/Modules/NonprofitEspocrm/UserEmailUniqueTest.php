<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Entities\EmailAddress;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Classes\DuplicateWhereBuilders\User as UserDuplicateWhereBuilder;
use Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\User\EmailAddress\UniqueAmongUsers;
use Espo\Modules\NonprofitEspocrm\Tools\UserEmailUniqueness;
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
class UserEmailUniqueTest extends TestCase
{
    public function testCollectAddressesNormalizesAndDedupes(): void
    {
        $user = $this->createMock(Entity::class);
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'emailAddress' => '  Foo@Example.COM ',
                'emailAddressData' => [
                    ['emailAddress' => 'foo@example.com'],
                    ['emailAddress' => 'bar@example.com'],
                ],
                default => null,
            }
        );

        $tool = new UserEmailUniqueness($this->createMock(EntityManager::class));

        $this->assertSame(
            ['foo@example.com', 'bar@example.com'],
            $tool->collectAddresses($user)
        );
    }

    public function testHasConflictWhenAnotherUserOwnsAddress(): void
    {
        $user = $this->userEntity('dup@example.com', 'user-self');
        $tool = new UserEmailUniqueness($this->entityManagerWithOwner('user-other'));

        $this->assertTrue($tool->hasConflict($user));
        $this->assertInstanceOf(
            Failure::class,
            (new UniqueAmongUsers($tool))->validate(
                $user,
                'emailAddress',
                new Data(new stdClass())
            )
        );
    }

    public function testSameUserIdIsNotConflict(): void
    {
        $user = $this->userEntity('mine@example.com', 'user-self');
        $tool = new UserEmailUniqueness($this->entityManagerWithOwner('user-self'));

        $this->assertFalse($tool->hasConflict($user));
        $this->assertNull(
            (new UniqueAmongUsers($tool))->validate(
                $user,
                'emailAddress',
                new Data(new stdClass())
            )
        );
    }

    public function testContactOwningAddressIsNotUserConflict(): void
    {
        $user = $this->userEntity('shared@example.com', 'user-new');
        $tool = new UserEmailUniqueness($this->entityManagerWithNoUserOwner());

        $this->assertFalse($tool->hasConflict($user));
    }

    public function testDuplicateWhereBuilderUsesCollectedAddresses(): void
    {
        $user = $this->userEntity('dup@example.com', 'user-self');
        $builder = new UserDuplicateWhereBuilder(
            new UserEmailUniqueness($this->createMock(EntityManager::class))
        );

        $this->assertNotNull($builder->build($user));
    }

    private function userEntity(string $email, string $id): Entity
    {
        $user = $this->createMock(Entity::class);
        $user->method('hasId')->willReturn($id !== '');
        $user->method('getId')->willReturn($id);
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'emailAddress' => $email,
                'emailAddressData' => null,
                default => null,
            }
        );

        return $user;
    }

    private function entityManagerWithOwner(string $ownerUserId): EntityManager
    {
        $email = $this->createMock(Entity::class);
        $email->method('getId')->willReturn('ea-1');

        $link = $this->createMock(Entity::class);
        $link->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'entityId' => $ownerUserId,
                default => null,
            }
        );

        $existingUser = $this->createMock(Entity::class);

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

        $userBuilder = $this->createMock(RDBSelectBuilder::class);
        $userBuilder->method('where')->willReturnSelf();
        $userBuilder->method('findOne')->willReturn($existingUser);

        $userRepo = $this->createMock(RDBRepository::class);
        $userRepo->method('select')->willReturn($userBuilder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->willReturnCallback(
            static function (string $type) use ($eaRepo, $linkRepo, $userRepo): RDBRepository {
                return match ($type) {
                    EmailAddress::ENTITY_TYPE => $eaRepo,
                    EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS => $linkRepo,
                    User::ENTITY_TYPE => $userRepo,
                    default => throw new \RuntimeException($type),
                };
            }
        );

        return $em;
    }

    private function entityManagerWithNoUserOwner(): EntityManager
    {
        $email = $this->createMock(Entity::class);
        $email->method('getId')->willReturn('ea-2');

        $link = $this->createMock(Entity::class);
        $link->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'entityId' => 'contact-1',
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

        $userBuilder = $this->createMock(RDBSelectBuilder::class);
        $userBuilder->method('where')->willReturnSelf();
        $userBuilder->method('findOne')->willReturn(null);

        $userRepo = $this->createMock(RDBRepository::class);
        $userRepo->method('select')->willReturn($userBuilder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->willReturnCallback(
            static function (string $type) use ($eaRepo, $linkRepo, $userRepo): RDBRepository {
                return match ($type) {
                    EmailAddress::ENTITY_TYPE => $eaRepo,
                    EmailAddress::RELATION_ENTITY_EMAIL_ADDRESS => $linkRepo,
                    User::ENTITY_TYPE => $userRepo,
                    default => throw new \RuntimeException($type),
                };
            }
        );

        return $em;
    }
}
