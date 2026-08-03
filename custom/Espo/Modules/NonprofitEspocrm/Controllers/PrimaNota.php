<?php

namespace Espo\Modules\NonprofitEspocrm\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\NonprofitEspocrm\Tools\PrimaNota\StripeRefreshService;
use stdClass;

class PrimaNota extends Record
{
    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws NotFound
     * @throws Error
     */
    public function postActionRefreshFromStripe(Request $request): stdClass
    {
        $data = $request->getParsedBody();
        $id = $data->id ?? null;

        if (!$id || !is_string($id)) {
            throw new BadRequest("No id.");
        }

        /** @var StripeRefreshService $service */
        $service = $this->injectableFactory->create(StripeRefreshService::class);

        return $service->refresh($id);
    }
}
