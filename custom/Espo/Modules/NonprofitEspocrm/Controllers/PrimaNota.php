<?php

namespace Espo\Modules\NonprofitEspocrm\Controllers;

use Espo\Core\Api\Request;
use Espo\Core\Controllers\Record;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\NonprofitEspocrm\Tools\PrimaNota\StripeBulkPullService;
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

    /**
     * @throws BadRequest
     * @throws Forbidden
     * @throws Error
     */
    public function postActionBulkPullFromProviders(Request $request): stdClass
    {
        $data = $request->getParsedBody() ?? (object) [];

        $providers = [];
        if (isset($data->providers) && is_array($data->providers)) {
            foreach ($data->providers as $provider) {
                if (is_string($provider) || is_numeric($provider)) {
                    $providers[] = (string) $provider;
                }
            }
        }

        $mode = isset($data->mode) && is_string($data->mode) ? $data->mode : 'all';
        $fromDate = isset($data->fromDate) && is_string($data->fromDate) ? $data->fromDate : null;
        $maxItems = isset($data->maxItems) && is_numeric($data->maxItems) ? (int) $data->maxItems : 200;

        $currencies = [];
        if (isset($data->currencies) && is_array($data->currencies)) {
            foreach ($data->currencies as $currency) {
                if (is_string($currency) || is_numeric($currency)) {
                    $currencies[] = (string) $currency;
                }
            }
        }

        $startingAfter = isset($data->startingAfter) && is_string($data->startingAfter)
            ? trim($data->startingAfter)
            : null;
        if ($startingAfter === '') {
            $startingAfter = null;
        }

        /** @var StripeBulkPullService $service */
        $service = $this->injectableFactory->create(StripeBulkPullService::class);

        @set_time_limit(600);

        return $service->pull($providers, $mode, $fromDate, $maxItems, $currencies, $startingAfter);
    }
}
