<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * AssignedUserDefaultApplier on record create (from bin/smoke-assigned-user-default.php).
 */
class AssignedUserDefaultTest extends SafehouseBaseTestCase
{
    public function testContactCreateDefaultsAssignedUserToCurrentUser(): void
    {
        $admin = $this->getAdminUser();
        $this->authenticate('admin');

        $em = $this->getEntityManager();
        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Assigned',
            'lastName' => 'Default ' . $this->uniqueMarker(),
        ]);
        $em->saveEntity($contact);

        $this->assertSame($admin->getId(), $contact->get('assignedUserId'));
    }

    public function testContactCreateKeepsExplicitAssignedUser(): void
    {
        $admin = $this->getAdminUser();
        $this->authenticate('admin');

        $em = $this->getEntityManager();
        $contact = $em->getNewEntity('Contact');
        $contact->set([
            'firstName' => 'Explicit',
            'lastName' => 'Assignee',
            'assignedUserId' => $admin->getId(),
        ]);
        $em->saveEntity($contact);

        $this->assertSame($admin->getId(), $contact->get('assignedUserId'));
    }
}
