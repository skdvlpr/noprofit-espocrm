<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\FieldProcessing\User;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Modules\NonprofitEspocrm\Tools\UserContactProfileSync;
use Espo\ORM\Entity;

/**
 * Populate User volunteering/member staging fields from linked Contact.
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
