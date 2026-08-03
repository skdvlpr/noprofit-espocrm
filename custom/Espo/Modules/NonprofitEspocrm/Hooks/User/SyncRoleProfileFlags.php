<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\User;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\UserContactProfileSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Keep hasVolunteerRole / hasMemberRole flags in sync with selected roles.
 *
 * @implements BeforeSave<\Espo\Entities\User>
 */
class SyncRoleProfileFlags implements BeforeSave
{
    public static int $order = 5;

    public function __construct(
        private UserContactProfileSync $userContactProfileSync
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($entity->get('type') === 'portal') {
            return;
        }

        $this->userContactProfileSync->applyRoleFlags($entity);

        if (
            $entity->get('weeklyHours') !== null
            && $entity->get('weeklyHours') !== ''
        ) {
            $entity->set(
                'monthlyHours',
                round((float) $entity->get('weeklyHours') * 4.33, 1)
            );
        }
    }
}
