<?php

namespace Espo\Modules\VolunteerActivityDispatch\Hooks\ActivityInvite;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * ActivityInvite rows must be created by {@see \Espo\Modules\VolunteerActivityDispatch\Tools\PublishService}.
 *
 * Without this gate, any user with ActivityInvite create + Task read can POST an invite for
 * themselves and Accept it, becoming a Task collaborator without Task edit ACL.
 */
class ProtectInviteCreate implements BeforeSave
{
    public const SAVE_OPTION = 'activityInvitePublishing';

    public static int $order = 4;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        if ($options->get(self::SAVE_OPTION)) {
            return;
        }

        throw new Forbidden(
            'ActivityInvite records can only be created by publishing an ActivityOffer.'
        );
    }
}
