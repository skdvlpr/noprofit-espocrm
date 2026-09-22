<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\User;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\Hook\Hook\AfterSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\RemoveOptions;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Linked Contacts become Inactive when the User is deleted or Is Active is unchecked.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
 *
 * @implements AfterRemove<\Espo\Entities\User>
 * @implements AfterSave<\Espo\Entities\User>
 */
class InactivateLinkedContacts implements AfterRemove, AfterSave
{
    public static int $order = 20;

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $this->inactivateLinked((string) $entity->getId());
    }

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (!$entity->isAttributeChanged('isActive')) {
            return;
        }

        if ($entity->get('isActive')) {
            return;
        }

        $this->inactivateLinked((string) $entity->getId());
    }

    private function inactivateLinked(string $userId): void
    {
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
