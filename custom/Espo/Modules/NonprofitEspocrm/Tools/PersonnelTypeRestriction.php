<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\ApplicationState;
use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;

/**
 * Volunteer / Employee in contactType is admin-only when those keys are added.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 */
class PersonnelTypeRestriction
{
    public function __construct(
        private ApplicationState $applicationState
    ) {}

    public function assertMaySave(Entity $entity): void
    {
        $new = ContactTypeSet::normalize($entity->get('contactType'));

        if (!ContactTypeSet::hasPersonnel($new)) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('contactType')) {
            return;
        }

        $old = ContactTypeSet::normalize($entity->getFetched('contactType'));
        $added = ContactTypeSet::addedPersonnel($new, $old);

        if (!$entity->isNew() && $added === []) {
            return;
        }

        if (!$this->applicationState->isLogged()) {
            return;
        }

        if ($this->applicationState->isAdmin()) {
            return;
        }

        throw new Forbidden("Only an administrator can set type Volunteer or Employee.");
    }
}
