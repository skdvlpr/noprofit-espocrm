<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Throwable;

/**
 * List Google calendars for a consented share target (manager fan-out pick UI).
 */
class GetShareTargetCalendars implements Action
{
    public function __construct(
        private User $user,
        private ManagerCalendarShare $managerCalendarShare,
        private ClientManager $clientManager,
        private CalendarProvisioner $calendarProvisioner,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->managerCalendarShare->actorCanShare($this->user)) {
            throw new Forbidden();
        }

        $targetUserId = trim((string) ($request->getQueryParam('userId') ?? ''));

        if ($targetUserId === '') {
            throw new BadRequest('userId is required.');
        }

        if (!$this->managerCalendarShare->canManageTargetCalendars($targetUserId)) {
            throw new Forbidden('Target user does not have Google Calendar connected.');
        }

        $list = [];
        $connected = true;
        $errorReason = null;
        $errorMessage = null;

        try {
            $client = $this->clientManager->create(Installer::INTEGRATION_ID, $targetUserId);

            if (!$client instanceof GoogleClient) {
                throw new Forbidden('Google account is not connected.');
            }

            foreach ($client->listCalendars() as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? '');

                if ($id === '') {
                    continue;
                }

                $summary = (string) ($item['summary'] ?? $id);

                $list[] = [
                    'id' => $id,
                    'summary' => $summary,
                    'isCrmCalendar' => $this->calendarProvisioner->isCrmCalendarName($summary),
                    'primary' => !empty($item['primary']),
                ];
            }
        } catch (Forbidden) {
            $connected = false;
            $errorReason = 'not_connected';
        } catch (Throwable $e) {
            $connected = false;
            $errorReason = 'api_error';
            $errorMessage = $e->getMessage();
        }

        [$namePrefix, $nameSuffix] = $this->calendarProvisioner->getPrefixSuffix();

        if (!$connected || $list === []) {
            $list = [['id' => 'primary', 'summary' => 'primary', 'isCrmCalendar' => false, 'primary' => true]];
        }

        return ResponseComposer::json([
            'list' => $list,
            'connected' => $connected,
            'errorReason' => $errorReason,
            'errorMessage' => $errorMessage,
            'namePrefix' => $namePrefix,
            'nameSuffix' => $nameSuffix,
            'userId' => $targetUserId,
        ]);
    }
}
