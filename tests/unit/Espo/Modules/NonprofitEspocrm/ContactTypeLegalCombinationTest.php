<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Contact\ContactType\LegalCombination;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class ContactTypeLegalCombinationTest extends TestCase
{
    /**
     * @dataProvider legalProvider
     * @param list<string>|string|null $value
     */
    public function testLegal(mixed $value): void
    {
        $this->assertTrue(ContactTypeSet::isLegal(ContactTypeSet::normalize($value)));
        $this->assertNull($this->validate($value));
    }

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function legalProvider(): array
    {
        return [
            'empty array' => [[]],
            'empty string' => [''],
            'null' => [null],
            'volunteer' => [['Volunteer']],
            'volunteer string' => ['Volunteer'],
            'member' => [['MemberContact']],
            'employee' => [['Employee']],
            'help seeker' => [['HelpSeeker']],
            'other' => [['Other']],
            'vol+member' => [['Volunteer', 'MemberContact']],
            'member+vol order' => [['MemberContact', 'Volunteer']],
            'emp+member' => [['Employee', 'MemberContact']],
        ];
    }

    /**
     * @dataProvider illegalProvider
     * @param list<string> $value
     */
    public function testIllegal(array $value): void
    {
        $this->assertFalse(ContactTypeSet::isLegal($value));
        $this->assertInstanceOf(Failure::class, $this->validate($value));
    }

    /**
     * @return array<string, array{0: list<string>}>
     */
    public static function illegalProvider(): array
    {
        return [
            'vol+employee' => [['Volunteer', 'Employee']],
            'member+help' => [['MemberContact', 'HelpSeeker']],
            'three' => [['Volunteer', 'MemberContact', 'Other']],
            'unknown' => [['NotAType']],
        ];
    }

    public function testRoleNamesAndDefaults(): void
    {
        $this->assertSame(
            ['Volunteer', 'Member'],
            ContactTypeSet::roleNames(['Volunteer', 'MemberContact'])
        );
        $this->assertSame([], ContactTypeSet::roleNames(['Other']));
        $this->assertTrue(ContactTypeSet::createUserDefaultOn(['Volunteer']));
        $this->assertTrue(ContactTypeSet::createUserDefaultOn(['MemberContact']));
        $this->assertFalse(ContactTypeSet::createUserDefaultOn(['Other']));
        $this->assertFalse(ContactTypeSet::createUserDefaultOn([]));
        $this->assertTrue(ContactTypeSet::wantsCrmUser(['MemberContact']));
        $this->assertFalse(ContactTypeSet::wantsCrmUser(['HelpSeeker']));
        $this->assertContains('Employee', ContactTypeSet::LEAD_OPTIONS);
    }

    public function testContainsWhereUsesArrayValueSubquery(): void
    {
        $where = ContactTypeSet::containsWhere('Contact', 'Volunteer');

        $this->assertArrayHasKey('id=s', $where);
        $this->assertIsArray($where['id=s']);
    }

    private function validate(mixed $value): ?Failure
    {
        $entity = $this->createMock(Entity::class);
        $entity->method('get')->willReturn($value);

        return (new LegalCombination())->validate(
            $entity,
            'contactType',
            new Data(new stdClass())
        );
    }
}
