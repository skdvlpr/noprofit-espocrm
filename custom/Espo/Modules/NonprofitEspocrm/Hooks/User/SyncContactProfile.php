<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\User;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Modules\NonprofitEspocrm\Tools\UserContactProfileSync;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Create/update linked Contact when User has Volunteer or Member role.
 *
 * @implements AfterSave<\Espo\Entities\User>
 */
class SyncContactProfile implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private UserContactProfileSync $userContactProfileSync
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($entity->get('type') === 'portal') {
            return;
        }

        $this->userContactProfileSync->syncFromUser($entity);
    }
}
