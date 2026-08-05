<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityInvite;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * ActivityInvite rows must be created/mutated by shift-planning services.
 *
 * Without this gate, a volunteer with ActivityInvite edit=own can:
 *   1. Own an availability invite (from saveAvailability),
 *   2. PUT taskId to any Task they can read (Volunteer Task read=all),
 *   3. Accept → InviteResponseService adds them to Task.collaborators
 *      without Task edit ACL.
 */
class ProtectInviteMutation implements BeforeSave
{
    public const SAVE_OPTION = 'activityInviteServiceSave';

    public static int $order = 4;

    /** @var list<string> */
    private const PROTECTED_ATTRIBUTES = [
        'taskId',
        'userId',
        'activityOfferId',
        'activityOfferSlotId',
        'status',
    ];

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($options->get(self::SAVE_OPTION)) {
            return;
        }

        if ($entity->isNew()) {
            throw new Forbidden(
                'ActivityInvite records can only be created via shift planning availability.'
            );
        }

        foreach (self::PROTECTED_ATTRIBUTES as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new Forbidden(
                    'ActivityInvite ' . $attribute . ' can only be changed via shift planning actions.'
                );
            }
        }
    }
}
