<?php

declare(strict_types=1);

namespace tests\unit\Espo\Modules\NonprofitEspocrm;

use Espo\Modules\NonprofitEspocrm\Tools\Admission\ContactAdmissionCopy;
use Espo\ORM\Entity;
use PHPUnit\Framework\TestCase;

/**
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/tests.md
 */
class ContactAdmissionCopyTest extends TestCase
{
    public function testSkipsNonConverted(): void
    {
        $lead = $this->entity([
            'status' => 'Assigned',
            'createdContactId' => 'c1',
            'contactType' => ['MemberContact'],
        ]);
        $contact = $this->entity([
            'id' => 'c1',
            'contactType' => ['MemberContact'],
        ]);

        $this->assertFalse(ContactAdmissionCopy::shouldCopy($lead, $contact));
    }

    public function testSkipsVolunteerContact(): void
    {
        $lead = $this->entity([
            'status' => 'Converted',
            'createdContactId' => 'c1',
            'contactType' => ['MemberContact'],
        ]);
        $contact = $this->entity([
            'id' => 'c1',
            'contactType' => ['Volunteer'],
        ]);

        $this->assertFalse(ContactAdmissionCopy::shouldCopy($lead, $contact));
    }

    public function testCopiesEmptyBoardAndFile(): void
    {
        $lead = $this->entity([
            'status' => 'Converted',
            'createdContactId' => 'c1',
            'contactType' => ['MemberContact'],
            'admissionOutcome' => 'Approved',
            'newsletterConsent' => 'Yes',
            'admissionFormId' => 'file-1',
            'admissionFormName' => 'domanda.pdf',
        ]);
        $contact = $this->entity([
            'id' => 'c1',
            'contactType' => ['MemberContact'],
        ]);

        $this->assertTrue(ContactAdmissionCopy::apply($lead, $contact));
        $this->assertSame('Approved', $contact->get('admissionOutcome'));
        $this->assertSame('Yes', $contact->get('newsletterConsent'));
        $this->assertSame('file-1', $contact->get('admissionFormId'));
    }

    public function testDoesNotOverwriteFilledContact(): void
    {
        $lead = $this->entity([
            'status' => 'Converted',
            'createdContactId' => 'c1',
            'contactType' => ['MemberContact'],
            'admissionOutcome' => 'Approved',
            'admissionFormId' => 'lead-file',
        ]);
        $contact = $this->entity([
            'id' => 'c1',
            'contactType' => ['MemberContact'],
            'admissionOutcome' => 'Rejected',
            'admissionFormId' => 'contact-file',
        ]);

        $this->assertFalse(ContactAdmissionCopy::apply($lead, $contact));
        $this->assertSame('Rejected', $contact->get('admissionOutcome'));
        $this->assertSame('contact-file', $contact->get('admissionFormId'));
    }

    /**
     * @param array<string, mixed> $values
     */
    private function entity(array $values): Entity
    {
        $bag = (object) $values;
        $id = (string) ($values['id'] ?? 'x');
        $entity = $this->createMock(Entity::class);
        $entity->method('getId')->willReturn($id);
        $entity->method('get')->willReturnCallback(
            static fn (string $field): mixed => property_exists($bag, $field) ? $bag->{$field} : null
        );
        $entity->method('set')->willReturnCallback(
            function (string $field, mixed $value) use ($bag, $entity): Entity {
                $bag->{$field} = $value;

                return $entity;
            }
        );

        return $entity;
    }
}
