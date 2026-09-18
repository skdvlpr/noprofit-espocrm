<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\FieldProcessing\User;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\NonprofitEspocrm\Tools\UserContactProfileSync;
use Espo\ORM\Entity;

/**
 * Populate User volunteering/member fields from linked Contact (read-only
 * reflection, not a stored copy).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/record-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements Loader<\Espo\Entities\User>
 */
class ContactProfileLoader implements Loader
{
    public function __construct(
        private UserContactProfileSync $userContactProfileSync
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        if ($entity->get('type') === 'portal') {
            return;
        }

        $this->userContactProfileSync->applyRoleFlags($entity);
        $this->userContactProfileSync->loadFromContact($entity);
    }
}
