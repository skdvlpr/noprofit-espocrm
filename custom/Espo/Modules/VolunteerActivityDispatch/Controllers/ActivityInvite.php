<?php

namespace Espo\Modules\VolunteerActivityDispatch\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\VolunteerActivityDispatch\Tools\InviteResponseService;
use stdClass;

class ActivityInvite extends Record
{
    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function postActionAccept(Request $request): stdClass
    {
        return $this->respond($request, InviteResponseService::STATUS_ACCEPTED);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function postActionDecline(Request $request): stdClass
    {
        return $this->respond($request, InviteResponseService::STATUS_DECLINED);
    }

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    private function respond(Request $request, string $status): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id || !is_string($id)) {
            throw new BadRequest("No id.");
        }

        $service = $this->injectableFactory->create(InviteResponseService::class);

        $invite = $status === InviteResponseService::STATUS_ACCEPTED
            ? $service->accept($id)
            : $service->decline($id);

        return $invite->getValueMap();
    }
}
