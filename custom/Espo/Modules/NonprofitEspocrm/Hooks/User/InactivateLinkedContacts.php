<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\User;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOption;

/**
 * When a User is deleted, linked Contacts become Inactive (Contact itself stays).
 *
 * @implements AfterRemove<\Espo\Entities\User>
 */
class InactivateLinkedContacts implements AfterRemove
{
    public static int $order = 20;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $userId = (string) $entity->getId();

        if ($userId === '') {
            return;
        }

        $contacts = $this->entityManager
            ->getRDBRepository('Contact')
            ->where([
                'OR' => [
                    ['linkedUserId' => $userId],
                    ['portalUserId' => $userId],
                ],
            ])
            ->find();

        foreach ($contacts as $contact) {
            if ($contact->get('personnelStatus') === 'Inactive') {
                continue;
            }

            $contact->set('personnelStatus', 'Inactive');

            $this->entityManager->saveEntity($contact, [
                SaveOption::SKIP_ALL => true,
            ]);
        }
    }
}
