<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;

class PostGoogleEvent implements Action
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

        $data = $request->getParsedBody();
        $calendarId = is_string($data->calendarId ?? null) && $data->calendarId !== '' ? $data->calendarId : 'primary';
        $event = (array) ($data->event ?? $data);
        unset($event['calendarId']);

        return ResponseComposer::json($this->googleClientProvider->get()->createCalendarEvent($event, $calendarId));
    }
}
