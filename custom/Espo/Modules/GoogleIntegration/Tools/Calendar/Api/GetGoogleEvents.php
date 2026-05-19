<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;

class GetGoogleEvents implements Action
{
    public function __construct(
        private Acl $acl,
        private GoogleClientProvider $googleClientProvider
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->check('Calendar')) {
            throw new Forbidden();
        }

        $calendarId = $request->getQueryParam('calendarId') ?: 'primary';
        $timeMin = $request->getQueryParam('timeMin') ?: $request->getQueryParam('from');
        $timeMax = $request->getQueryParam('timeMax') ?: $request->getQueryParam('to');

        if (!is_string($timeMin) || !is_string($timeMax) || $timeMin === '' || $timeMax === '') {
            throw new BadRequest('timeMin and timeMax are required.');
        }

        $result = $this->googleClientProvider->get()->listCalendarEvents(
            (string) $calendarId,
            $timeMin,
            $timeMax,
            250,
            $request->getQueryParam('pageToken')
        );

        return ResponseComposer::json($result);
    }
}
