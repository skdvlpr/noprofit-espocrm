<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;

class PutGoogleEvent implements Action
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

        $calendarId = $request->getRouteParam('calendarId') ?? throw new BadRequest();
        $eventId = $request->getRouteParam('eventId') ?? throw new BadRequest();

        $data = $request->getParsedBody();
        $event = (array) ($data->event ?? $data);

        return ResponseComposer::json($this->googleClientProvider->get()->updateCalendarEvent($eventId, $event, $calendarId));
    }
}
