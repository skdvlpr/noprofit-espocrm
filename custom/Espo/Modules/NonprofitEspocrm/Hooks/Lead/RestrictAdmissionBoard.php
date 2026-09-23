<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Lead;

use Espo\Core\ApplicationState;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionBoard;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\LeadTaxCode;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\NewsletterConsent;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Board section is admin or Role name Member. Tax code is only normalized.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements BeforeSave<\Espo\Modules\Crm\Entities\Lead>
 */
class RestrictAdmissionBoard implements BeforeSave
{
    public static int $order = 8;

    public function __construct(
        private ApplicationState $applicationState,
        private EntityManager $entityManager,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $taxCode = LeadTaxCode::normalize($entity->get('taxCode'));

        if ($taxCode !== $entity->get('taxCode')) {
            $entity->set('taxCode', $taxCode);
        }

        NewsletterConsent::sync($entity);

        $logged = $this->applicationState->isLogged();
        $admin = $logged && $this->applicationState->isAdmin();
        $roleNames = [];

        if ($logged && !$admin) {
            $user = $this->applicationState->getUser();
            $roles = $this->entityManager->getRelation($user, 'roles')->find();

            foreach ($roles as $role) {
                $name = $role->get('name');

                if (is_string($name) && $name !== '') {
                    $roleNames[] = $name;
                }
            }
        }

        AdmissionBoard::assertMaySave($entity, $logged, $admin, $roleNames);
    }
}
