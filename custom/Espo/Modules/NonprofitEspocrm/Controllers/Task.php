<?php

namespace Espo\Modules\NonprofitEspocrm\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\Crm\Controllers\Task as BaseTask;
use Espo\Modules\NonprofitEspocrm\Tools\InviteResponseService;
use stdClass;

class Task extends BaseTask
{
    /**
     * Accept/decline the current user's ActivityInvite for this Task.
     *
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     */
    public function postActionRespondInvite(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;
        $status = $data->status ?? null;

        if (!$id || !is_string($id) || !is_string($status)) {
            throw new BadRequest("No id or status.");
        }

        $invite = $this->injectableFactory
            ->create(InviteResponseService::class)
            ->respondForTask($id, $status);

        return $invite->getValueMap();
    }
}
