<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Case.websiteReferenceId minting (AssignWebsiteReferenceId hook).
 */
class CaseWebsiteReferenceTest extends SafehouseBaseTestCase
{
    public function testAssistanceRequestMintsShPrefix(): void
    {
        $em = $this->getEntityManager();
        $contact = $this->seedContact();

        $case = $em->getNewEntity('Case');
        $case->set([
            'name' => 'PHPUnit assistance ' . $this->uniqueMarker(),
            'type' => 'AssistanceRequest',
            'parentType' => 'Contact',
            'parentId' => $contact->getId(),
        ]);
        $em->saveEntity($case);

        $ref = (string) $case->get('websiteReferenceId');
        $this->assertNotSame('', $ref);
        $this->assertStringStartsWith('sh-', $ref);
    }

    public function testRichiestaGenericaMintsRgPrefix(): void
    {
        $em = $this->getEntityManager();
        $contact = $this->seedContact();

        $case = $em->getNewEntity('Case');
        $case->set([
            'name' => 'PHPUnit generic ' . $this->uniqueMarker(),
            'type' => 'RichiestaGenerica',
            'parentType' => 'Contact',
            'parentId' => $contact->getId(),
        ]);
        $em->saveEntity($case);

        $ref = (string) $case->get('websiteReferenceId');
        $this->assertStringStartsWith('rg-', $ref);
    }

    public function testExistingWebsiteReferenceIsNotOverwrittenOnResave(): void
    {
        $em = $this->getEntityManager();
        $contact = $this->seedContact();

        $case = $em->getNewEntity('Case');
        $case->set([
            'name' => 'PHPUnit resave ' . $this->uniqueMarker(),
            'type' => 'AssistanceRequest',
            'parentType' => 'Contact',
            'parentId' => $contact->getId(),
        ]);
        $em->saveEntity($case);

        $original = (string) $case->get('websiteReferenceId');
        $case = $em->getEntityById('Case', $case->getId());
        $case->set('websiteReferenceId', 'manual-override-attempt');
        $case->set('name', 'PHPUnit resave updated');
        $em->saveEntity($case);

        $this->assertSame($original, $case->get('websiteReferenceId'));
    }

    private function seedContact(): \Espo\ORM\Entity
    {
        $em = $this->getEntityManager();
        $marker = $this->uniqueMarker();

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'CaseParent',
            'emailAddress' => 'case-parent-' . $marker . '@example.com',
        ]);
        $em->saveEntity($contact);

        return $contact;
    }
}
