<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityOffer;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Lifecycle statuses that create side effects must go through ShiftPlanningService.
 *
 * Setting CollectingAvailability / Planned / Confirmed directly would skip
 * cohort notifications, auto-assignment, Tasks, and collaborator sync.
 */
class ProtectPlanStatus implements BeforeSave
{
    public const SAVE_OPTION = 'activityOfferPlanServiceSave';

    public static int $order = 5;

    /** @var list<string> */
    private const GATED_STATUSES = [
        'CollectingAvailability',
        'Planned',
        'Confirmed',
    ];

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if ($options->get(self::SAVE_OPTION)) {
            return;
        }

        $status = (string) ($entity->get('status') ?? '');

        if (!in_array($status, self::GATED_STATUSES, true)) {
            return;
        }

        if ($entity->isNew() || $entity->isAttributeChanged('status')) {
            throw new BadRequest(
                'Use shift planning actions (request availability, auto-assign, confirm plan) '
                . 'to advance ActivityOffer status. Setting "' . $status . '" directly skips '
                . 'required side effects.'
            );
        }
    }
}
