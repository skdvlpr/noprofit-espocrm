<?php

namespace Espo\Modules\NonprofitEspocrm\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use stdClass;

class ActivityOffer extends Record
{
    /**
     * @throws BadRequest|Forbidden|NotFound|Error
     */
    public function postActionRequestAvailability(Request $request): stdClass
    {
        return (object) $this->createService()
            ->requestAvailability($this->requireId($request));
    }

    /**
     * @throws BadRequest|Forbidden|NotFound
     */
    public function postActionAutoAssign(Request $request): stdClass
    {
        return (object) $this->createService()
            ->autoAssign($this->requireId($request));
    }

    /**
     * @throws BadRequest|Forbidden|NotFound
     */
    public function postActionConfirmPlan(Request $request): stdClass
    {
        return (object) $this->createService()
            ->confirm($this->requireId($request));
    }

    /**
     * @throws BadRequest|Forbidden|NotFound
     */
    public function getActionAvailabilityGrid(Request $request): stdClass
    {
        $id = $request->getQueryParam('id');

        if (!$id || !is_string($id)) {
            throw new BadRequest("No id.");
        }

        return (object) $this->createService()->availabilityGrid($id);
    }

    /**
     * @throws BadRequest|Forbidden|NotFound
     */
    public function postActionSaveAvailability(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;
        $slotIds = $data->slotIds ?? null;

        if (!$id || !is_string($id) || !is_array($slotIds)) {
            throw new BadRequest("No id or slotIds.");
        }

        return (object) $this->createService()->saveAvailability(
            $id,
            $slotIds,
            is_string($data->comment ?? null) ? $data->comment : null
        );
    }

    /**
     * @throws BadRequest|Forbidden|NotFound
     */
    public function getActionCoverage(Request $request): stdClass
    {
        $id = $request->getQueryParam('id');

        if (!$id || !is_string($id)) {
            throw new BadRequest("No id.");
        }

        return (object) $this->createService()->coverage($id);
    }

    /**
     * Append a batch of weekly shifts (mass create from Turni panel).
     *
     * @throws BadRequest|Forbidden|NotFound|Error
     */
    public function postActionAddWeekSlots(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;
        $rows = $data->rows ?? null;

        if (!$id || !is_string($id) || !is_array($rows)) {
            throw new BadRequest("No id or rows.");
        }

        $batchOptions = [
            'uniqueAddress' => !empty($data->uniqueAddress),
            'placeStreet' => is_string($data->placeStreet ?? null) ? $data->placeStreet : '',
            'placeCity' => is_string($data->placeCity ?? null) ? $data->placeCity : '',
            'placeState' => is_string($data->placeState ?? null) ? $data->placeState : '',
            'placeCountry' => is_string($data->placeCountry ?? null) ? $data->placeCountry : '',
            'placePostalCode' => is_string($data->placePostalCode ?? null) ? $data->placePostalCode : '',
        ];

        return (object) $this->createService()->addWeekSlots($id, $rows, $batchOptions);
    }

    private function createService(): ShiftPlanningService
    {
        return $this->injectableFactory->create(ShiftPlanningService::class);
    }

    /**
     * @throws BadRequest
     */
    private function requireId(Request $request): string
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id || !is_string($id)) {
            throw new BadRequest("No id.");
        }

        return $id;
    }
}
