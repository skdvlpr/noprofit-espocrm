<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\Exceptions\BadRequest;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Intervention + Case intake hooks (converted from bin/smoke-intervention.php / smoke-case-intake.php).
 */
class InterventionCaseIntakeTest extends SafehouseBaseTestCase
{
    public function testInterventionRequiresParentAndSetsDisplayName(): void
    {
        $em = $this->getEntityManager();

        $withoutParent = $em->getNewEntity('Intervention');
        $withoutParent->set([
            'description' => 'PHPUnit missing parent',
            'department' => 'StreetUnit',
            'interventionDate' => date('Y-m-d'),
            'interventionCount' => 1,
        ]);

        try {
            $em->saveEntity($withoutParent);
            $this->fail('Expected BadRequest when Intervention has no parent.');
        } catch (BadRequest $e) {
            $this->assertStringContainsString('requires a related CRM record', $e->getMessage());
        }

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'InterventionParent',
        ]);
        $em->saveEntity($contact);

        $intervention = $em->getNewEntity('Intervention');
        $intervention->set([
            'description' => 'PHPUnit intervention',
            'department' => 'StreetUnit',
            'interventionDate' => date('Y-m-d'),
            'interventionCount' => 2,
            'parentType' => 'Contact',
            'parentId' => $contact->getId(),
        ]);
        $em->saveEntity($intervention);

        $this->assertNotEmpty($intervention->get('name'));
        $this->assertStringContainsString((string) $intervention->get('interventionDate'), (string) $intervention->get('name'));
    }

    public function testCaseRequiresParentAndMintsWebsiteReferenceId(): void
    {
        $em = $this->getEntityManager();

        $withoutParent = $em->getNewEntity('Case');
        $withoutParent->set([
            'name' => 'PHPUnit case missing parent',
            'type' => 'AssistanceRequest',
        ]);

        try {
            $em->saveEntity($withoutParent);
            $this->fail('Expected BadRequest when Case has no parent.');
        } catch (BadRequest $e) {
            $this->assertStringContainsString('requires a related CRM record', $e->getMessage());
        }

        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'PHPUnit',
            'lastName' => 'CaseParent',
        ]);
        $em->saveEntity($contact);

        $case = $em->getNewEntity('Case');
        $case->set([
            'name' => 'PHPUnit case with parent',
            'type' => 'RichiestaGenerica',
            'parentType' => 'Contact',
            'parentId' => $contact->getId(),
        ]);
        $em->saveEntity($case);

        $ref = (string) $case->get('websiteReferenceId');
        $this->assertStringStartsWith('rg-', $ref);
        $this->assertMatchesRegularExpression(
            '/^rg-[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/',
            $ref
        );

        $originalRef = $ref;
        $case->set('websiteReferenceId', 'rg-should-not-overwrite');
        $case->set('description', 'touch protect');
        $em->saveEntity($case);

        $protected = $em->getEntityById('Case', $case->getId());
        $this->assertSame($originalRef, $protected->get('websiteReferenceId'));
    }
}
