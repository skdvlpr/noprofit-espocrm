<?php

namespace Espo\Modules\VolunteerActivityDispatch\Hooks\ActivityInvite;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Accept/Decline must go through {@see \Espo\Modules\VolunteerActivityDispatch\Tools\InviteResponseService}
 * so Task.collaborators stays in sync with invite status.
 */
class ProtectResponseStatus implements BeforeSave
{
    public const SAVE_OPTION = 'activityInviteResponding';

    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($options->get(self::SAVE_OPTION)) {
            return;
        }

        $status = (string) ($entity->get('status') ?? 'Pending');

        if ($entity->isNew()) {
            if ($status !== '' && $status !== 'Pending') {
                throw new BadRequest(
                    'ActivityInvite status can only be changed via Accept/Decline actions.'
                );
            }

            return;
        }

        if ($entity->isAttributeChanged('status')) {
            throw new BadRequest(
                'ActivityInvite status can only be changed via Accept/Decline actions.'
            );
        }
    }
}
