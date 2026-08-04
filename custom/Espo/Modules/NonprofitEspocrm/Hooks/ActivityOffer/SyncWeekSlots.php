<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\ActivityOffer;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Materialises the WhatsApp-style weekly shift generator (`weekSlots`
 * notStorable payload) into ActivityOfferSlot records on save.
 */
class SyncWeekSlots implements AfterSave
{
    public static int $order = 20;

    public function __construct(
        private ShiftPlanningService $shiftPlanningService,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SKIP_HOOKS)) {
            return;
        }

        if (!$entity->has('weekSlots')) {
            return;
        }

        $rows = $entity->get('weekSlots');

        if ($rows === null) {
            return;
        }

        if (!is_array($rows)) {
            return;
        }

        $this->shiftPlanningService->syncWeekSlots(
            $entity->getId(),
            $rows,
            false,
            [
                'uniqueAddress' => (bool) $entity->get('uniqueAddress'),
                'placeStreet' => (string) ($entity->get('placeStreet') ?? ''),
                'placeCity' => (string) ($entity->get('placeCity') ?? ''),
                'placeState' => (string) ($entity->get('placeState') ?? ''),
                'placeCountry' => (string) ($entity->get('placeCountry') ?? ''),
                'placePostalCode' => (string) ($entity->get('placePostalCode') ?? ''),
            ]
        );
    }
}
