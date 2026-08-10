<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ExternalAccount\ClientManager;
use Espo\Entities\User;
use Espo\Modules\GoogleIntegration\Core\ExternalAccount\Clients\Google as GoogleClient;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\ManagerCalendarShare;
use Espo\Modules\GoogleIntegration\Tools\Installer;
use Throwable;

/**
 * Create a Google calendar on a consented share target account (prefix/suffix locked).
 */
class PostShareTargetCalendar implements Action
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

        $body = $request->getParsedBody();
        $targetUserId = trim((string) ($body->userId ?? ''));

        if ($targetUserId === '') {
            throw new BadRequest('userId is required.');
        }

        if (!$this->managerCalendarShare->canManageTargetCalendars($targetUserId)) {
            throw new Forbidden('Target user does not have Google Calendar connected.');
        }

        $label = trim((string) ($body->label ?? ''));
        $summaryRaw = trim((string) ($body->summary ?? ''));

        if ($label === '' && $summaryRaw !== '') {
            $label = $this->calendarProvisioner->extractLabelFromCalendarName($summaryRaw);
        }

        if ($label === '') {
            throw new BadRequest('Calendar name is required.');
        }

        if (mb_strlen($label) > 200) {
            throw new BadRequest('Calendar name is too long.');
        }

        $summary = $this->calendarProvisioner->buildCalendarName($label);

        if ($summary === '' || mb_strlen($summary) > 200) {
            throw new BadRequest('Calendar name is required.');
        }

        try {
            $client = $this->clientManager->create(Installer::INTEGRATION_ID, $targetUserId);

            if (!$client instanceof GoogleClient) {
                throw new Forbidden('Google account is not connected.');
            }

            $existingId = $client->findCalendarIdBySummary($summary);

            if ($existingId !== null) {
                return ResponseComposer::json([
                    'id' => $existingId,
                    'summary' => $summary,
                    'label' => $label,
                    'created' => false,
                    'userId' => $targetUserId,
                ]);
            }

            $created = $client->insertCalendar($summary);
            $id = trim((string) ($created['id'] ?? ''));

            if ($id === '') {
                throw new Error('Google calendar create returned no id.');
            }

            return ResponseComposer::json([
                'id' => $id,
                'summary' => (string) ($created['summary'] ?? $summary),
                'label' => $label,
                'created' => true,
                'userId' => $targetUserId,
            ]);
        } catch (Forbidden $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new Error('Could not create Google calendar: ' . $e->getMessage());
        }
    }
}
