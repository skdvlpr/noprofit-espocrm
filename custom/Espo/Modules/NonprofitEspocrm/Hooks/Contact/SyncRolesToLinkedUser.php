<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Contact;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Entities\User;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep linked User Roles Volunteer / Employee / Member in sync with contactType.
 * Other Roles are left untouched. Lookup by Role name (not id).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
 *
 * @implements AfterSave<\Espo\Modules\Crm\Entities\Contact>
 */
class SyncRolesToLinkedUser implements AfterSave
{
    public static int $order = 20;

    /** @var list<string> */
    private const MANAGED_ROLE_NAMES = [
        ContactTypeSet::ROLE_VOLUNTEER,
        ContactTypeSet::ROLE_EMPLOYEE,
        ContactTypeSet::ROLE_MEMBER,
    ];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        $userId = (string) ($entity->get('linkedUserId') ?? '');

        if ($userId === '') {
            return;
        }

        if (
            !$entity->isNew()
            && !$entity->isAttributeChanged('contactType')
            && !$entity->isAttributeChanged('linkedUserId')
        ) {
            return;
        }

        $user = $this->entityManager->getEntityById(User::ENTITY_TYPE, $userId);

        if (!$user || (string) ($user->get('type') ?? '') === 'portal') {
            return;
        }

        $wanted = ContactTypeSet::roleNames(
            ContactTypeSet::normalize($entity->get('contactType'))
        );

        $roles = $this->entityManager
            ->getRDBRepository('Role')
            ->where(['name' => self::MANAGED_ROLE_NAMES])
            ->find();

        $relation = $this->entityManager
            ->getRDBRepository(User::ENTITY_TYPE)
            ->getRelation($user, 'roles');

        $currentIds = [];

        foreach ($relation->find() as $existing) {
            $currentIds[] = $existing->getId();
        }

        foreach ($roles as $role) {
            $name = (string) $role->get('name');
            $id = $role->getId();
            $shouldHave = in_array($name, $wanted, true);
            $has = in_array($id, $currentIds, true);

            if ($shouldHave && !$has) {
                $relation->relate($role);
            } elseif (!$shouldHave && $has) {
                $relation->unrelate($role);
            }
        }
    }
}
