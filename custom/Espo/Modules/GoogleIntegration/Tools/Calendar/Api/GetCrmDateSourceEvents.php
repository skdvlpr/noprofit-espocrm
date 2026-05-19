<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\Crm\Tools\Calendar\Item as CalendarItem;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CrmDateSourceEventFetcher;

class GetCrmDateSourceEvents implements Action
{
    public function __construct(
        private Acl $acl,
        private CrmDateSourceEventFetcher $fetcher
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->check('Calendar')) {
            throw new Forbidden();
        }

        $from = $request->getQueryParam('from');
        $to = $request->getQueryParam('to');

        if (empty($from) || empty($to)) {
            throw new BadRequest();
        }

        $scopeList = null;

        if ($request->getQueryParam('scopeList') !== null) {
            $scopeList = array_values(array_filter(explode(',', $request->getQueryParam('scopeList'))));
        }

        $itemList = $this->fetcher->fetch($from, $to, $scopeList);

        return ResponseComposer::json(array_map(
            fn (CalendarItem $item) => $item->getRaw(),
            $itemList
        ));
    }
}
