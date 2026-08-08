<?php

namespace Espo\Modules\NonprofitEspocrm\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\NonprofitEspocrm\Tools\ShiftPlanningService;
use stdClass;

class ActivityOfferSlot extends Record
{
    /**
     * Assigned + available candidates for one shift.
     *
     * @throws BadRequest|Forbidden|NotFound
     */
    public function getActionStaffing(Request $request): stdClass
    {
        $id = $request->getQueryParam('id');

        if (!$id || !is_string($id)) {
            throw new BadRequest('No id.');
        }

        return (object) $this->createService()->slotStaffing($id);
    }

    /**
     * Re-send availability invitation to one candidate on this shift.
     *
     * @throws BadRequest|Forbidden|NotFound
     */
    public function postActionResendInvite(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;
        $userId = $data->userId ?? null;

        if (!$id || !is_string($id) || !$userId || !is_string($userId)) {
            throw new BadRequest('No id or userId.');
        }

        return (object) $this->createService()->resendSlotInvite($id, $userId);
    }

    /**
     * Annul one shift (Published/Covered → Cancelled) and notify Assigned/Confirmed.
     *
     * @throws BadRequest|Forbidden|NotFound
     */
    public function postActionCancel(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id || !is_string($id)) {
            throw new BadRequest('No id.');
        }

        return (object) $this->createService()->cancelSlot($id);
    }

    private function createService(): ShiftPlanningService
    {
        return $this->injectableFactory->create(ShiftPlanningService::class);
    }
}
