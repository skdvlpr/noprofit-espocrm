<?php

namespace Espo\Modules\VolunteerActivityDispatch\Hooks\ActivityOffer;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Status Published must only be set by {@see \Espo\Modules\VolunteerActivityDispatch\Tools\PublishService}.
 * Manual/API status edits would mark the offer Published without Tasks, invites, or notifications.
 */
class ProtectPublishStatus implements BeforeSave
{
    public const SAVE_OPTION = 'activityOfferPublishing';

    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($options->get(self::SAVE_OPTION)) {
            return;
        }

        $status = (string) ($entity->get('status') ?? '');

        if ($status !== 'Published') {
            return;
        }

        if ($entity->isNew() || $entity->isAttributeChanged('status')) {
            throw new BadRequest(
                'Use the Publish week action to publish an ActivityOffer. '
                . 'Setting status to Published directly skips Tasks, invites, and notifications.'
            );
        }
    }
}
