<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\GoogleIntegration\Tools\Calendar\AllowedEntityTypesProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateCapableEntityTypesProvider;
use Throwable;

/**
 * Create a Google calendar for the current user (user_pick "Create new calendar" UI).
 *
 * Prefix/suffix come only from Admin → Integrations → Google. The client may send
 * a middle `label` (preferred) or a full `summary` (legacy); the final Google name
 * is always rebuilt via CalendarProvisioner::buildCalendarName.
 */
class PostGoogleCalendar implements Action
{
    public function __construct(
        private Acl $acl,
        private GoogleClientProvider $googleClientProvider,
        private AllowedEntityTypesProvider $allowedEntityTypesProvider,
        private DateCapableEntityTypesProvider $dateCapableEntityTypesProvider,
        private CalendarProvisioner $calendarProvisioner,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->userMayManageCalendars()) {
            throw new Forbidden();
        }

        $body = $request->getParsedBody();
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

        if ($summary === '') {
            throw new BadRequest('Calendar name is required.');
        }

        if (mb_strlen($summary) > 200) {
            throw new BadRequest('Calendar name is too long.');
        }

        try {
            $client = $this->googleClientProvider->get();
            $existingId = $client->findCalendarIdBySummary($summary);

            if ($existingId !== null) {
                return ResponseComposer::json([
                    'id' => $existingId,
                    'summary' => $summary,
                    'label' => $label,
                    'created' => false,
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
            ]);
        } catch (Forbidden $e) {
            throw $e;
        } catch (Throwable $e) {
            throw new Error('Could not create Google calendar: ' . $e->getMessage());
        }
    }

    private function userMayManageCalendars(): bool
    {
        foreach ($this->allowedEntityTypesProvider->getEntityTypeList() as $entityType) {
            if ($this->acl->checkScope($entityType, 'edit') || $this->acl->checkScope($entityType, 'create')) {
                return true;
            }
        }

        foreach ($this->dateCapableEntityTypesProvider->getEntityTypeList() as $entityType) {
            if ($this->acl->checkScope($entityType, 'edit') || $this->acl->checkScope($entityType, 'create')) {
                return true;
            }
        }

        return false;
    }
}
