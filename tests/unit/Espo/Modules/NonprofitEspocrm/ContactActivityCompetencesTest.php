<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\ContactActivityCompetences;
use Espo\ORM\Entity;
use Espo\ORM\EntityCollection;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\RDBRepository;
use Espo\ORM\Repository\RDBSelectBuilder;
use PHPUnit\Framework\TestCase;

class ContactActivityCompetencesTest extends TestCase
{
    public function testNormalizeListDropsNonStrings(): void
    {
        $this->assertSame([], ContactActivityCompetences::normalizeList(null));
        $this->assertSame(['MealDistribution'], ContactActivityCompetences::normalizeList(['MealDistribution', 1, null]));
    }

    public function testIsPersonnelType(): void
    {
        $this->assertTrue(ContactActivityCompetences::isPersonnelType('Volunteer'));
        $this->assertTrue(ContactActivityCompetences::isPersonnelType('Employee'));
        $this->assertFalse(ContactActivityCompetences::isPersonnelType('HelpSeeker'));
        $this->assertFalse(ContactActivityCompetences::isPersonnelType(null));
    }

    public function testListForUserReadsContactNotUserAttribute(): void
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'contactType' => 'Volunteer',
                'activityCompetences' => ['MealDistribution'],
                default => null,
            }
        );

        $reader = new ContactActivityCompetences($this->emWithContacts([$contact]));
        $user = $this->userWithId('user-1');
        $user->method('get')->willReturn(['MealPreparation']);

        $this->assertSame(['MealDistribution'], $reader->listForUser($user));
    }

    public function testListForUserEmptyWhenNoContact(): void
    {
        $reader = new ContactActivityCompetences($this->emWithContacts([]));
        $user = $this->userWithId('user-2');
        $user->method('get')->willReturn(['MealPreparation']);

        $this->assertSame([], $reader->listForUser($user));
    }

    public function testCopyFromUserWritesVolunteerContact(): void
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'contactType' ? 'Volunteer' : null
        );
        $contact->expects($this->once())
            ->method('set')
            ->with('activityCompetences', ['MealDistribution'])
            ->willReturnSelf();

        $em = $this->emWithContacts([$contact]);
        $em->expects($this->once())->method('saveEntity');

        $user = $this->userWithId('user-3');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'activityCompetences' ? ['MealDistribution'] : null
        );

        $tool = new ContactActivityCompetences($em);
        $this->assertSame('copied', $tool->copyFromUser($user, true));
    }

    public function testCopyFromUserSkipsHelpSeeker(): void
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'contactType' ? 'HelpSeeker' : null
        );
        $contact->expects($this->never())->method('set');

        $em = $this->emWithContacts([$contact]);
        $em->expects($this->never())->method('saveEntity');

        $user = $this->userWithId('user-4');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'activityCompetences' ? ['MealDistribution'] : null
        );

        $tool = new ContactActivityCompetences($em);
        $this->assertSame('skipped-type', $tool->copyFromUser($user, true));
    }

    public function testCopyFromUserNoContact(): void
    {
        $em = $this->emWithContacts([]);
        $em->expects($this->never())->method('saveEntity');

        $user = $this->userWithId('user-5');
        $user->method('get')->willReturn([]);

        $tool = new ContactActivityCompetences($em);
        $this->assertSame('no-contact', $tool->copyFromUser($user, true));
    }

    public function testCopyDoesNotWipeContactWhenUserListEmpty(): void
    {
        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'contactType' => 'Volunteer',
                'activityCompetences' => ['MealDistribution'],
                default => null,
            }
        );
        $contact->expects($this->never())->method('set');

        $em = $this->emWithContacts([$contact]);
        $em->expects($this->never())->method('saveEntity');

        $user = $this->userWithId('user-6');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'activityCompetences' ? [] : null
        );

        $tool = new ContactActivityCompetences($em);
        $this->assertSame('skipped-empty', $tool->copyFromUser($user, true));
    }

    public function testCopyFromLeftoverColumnWhenContactAndOrmEmpty(): void
    {
        $stmt = $this->createMock(\PDOStatement::class);
        $stmt->method('execute')->willReturn(true);
        $stmt->method('fetchColumn')->willReturn('["MealDistribution"]');

        $pdo = $this->createMock(\PDO::class);
        $pdo->method('prepare')->willReturn($stmt);

        $contact = $this->createMock(Entity::class);
        $contact->method('get')->willReturnCallback(
            static fn (string $field): mixed => match ($field) {
                'contactType' => 'Volunteer',
                'activityCompetences' => [],
                default => null,
            }
        );
        $contact->expects($this->once())
            ->method('set')
            ->with('activityCompetences', ['MealDistribution'])
            ->willReturnSelf();

        $em = $this->emWithContacts([$contact]);
        $em->method('getPDO')->willReturn($pdo);
        $em->expects($this->once())->method('saveEntity');

        $user = $this->userWithId('user-7');
        $user->method('get')->willReturnCallback(
            static fn (string $field): mixed => $field === 'activityCompetences' ? [] : null
        );

        $tool = new ContactActivityCompetences($em);
        $this->assertSame('copied', $tool->copyFromUser($user, true));
    }

    /**
     * @param list<Entity> $contacts
     */
    private function emWithContacts(array $contacts): EntityManager
    {
        $builder = $this->createMock(RDBSelectBuilder::class);
        $builder->method('find')->willReturn(new EntityCollection($contacts, 'Contact'));

        $repo = $this->createMock(RDBRepository::class);
        $repo->method('where')->willReturn($builder);

        $em = $this->createMock(EntityManager::class);
        $em->method('getRDBRepository')->with('Contact')->willReturn($repo);

        return $em;
    }

    private function userWithId(string $id): Entity
    {
        $user = $this->createMock(Entity::class);
        $user->method('getId')->willReturn($id);

        return $user;
    }
}
