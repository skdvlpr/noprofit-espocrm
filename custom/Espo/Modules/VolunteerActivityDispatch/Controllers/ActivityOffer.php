<?php

namespace Espo\Modules\VolunteerActivityDispatch\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\VolunteerActivityDispatch\Tools\PublishService;
use stdClass;

class ActivityOffer extends Record
{
    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     */
    public function postActionPublish(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id || !is_string($id)) {
            throw new BadRequest("No id.");
        }

        $result = $this->injectableFactory
            ->create(PublishService::class)
            ->publish($id);

        return (object) $result;
    }
}
