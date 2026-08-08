<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\GoogleIntegration\Tools\Calendar\AllowedEntityTypesProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateCapableEntityTypesProvider;
use Throwable;

class GetGoogleCalendars implements Action
{
    public function __construct(
        private Acl $acl,
        private GoogleClientProvider $googleClientProvider,
        private AllowedEntityTypesProvider $allowedEntityTypesProvider,
        private DateCapableEntityTypesProvider $dateCapableEntityTypesProvider,
        private CalendarProvisioner $calendarProvisioner
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->userMayListCalendars()) {
            throw new Forbidden();
        }

        $list = [];
        $connected = true;
        $errorReason = null;
        $errorMessage = null;

        try {
            foreach ($this->googleClientProvider->get()->listCalendars() as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? '');

                if ($id === '') {
                    continue;
                }

                $summary = (string) ($item['summary'] ?? $id);
                $isCrm = $this->calendarProvisioner->isCrmCalendarName($summary);
                $isPrimary = !empty($item['primary']);

                $list[] = [
                    'id' => $id,
                    'summary' => $summary,
                    'isCrmCalendar' => $isCrm,
                    'primary' => $isPrimary,
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

        $forOverlay = $request->getQueryParam('forOverlay') === '1'
            || $request->getQueryParam('forOverlay') === 'true';

        if ($forOverlay) {
            $list = array_values(array_filter(
                $list,
                static fn (array $row): bool => empty($row['isCrmCalendar'])
            ));
        }

        if (!$connected) {
            return ResponseComposer::json([
                'list' => [['id' => 'primary', 'summary' => 'primary', 'isCrmCalendar' => false, 'primary' => true]],
                'connected' => false,
                'errorReason' => $errorReason,
                'errorMessage' => $errorMessage,
                'namePrefix' => $namePrefix,
                'nameSuffix' => $nameSuffix,
            ]);
        }

        if ($list === []) {
            $list[] = ['id' => 'primary', 'summary' => 'primary', 'isCrmCalendar' => false, 'primary' => true];
        }

        return ResponseComposer::json([
            'list' => $list,
            'connected' => true,
            'namePrefix' => $namePrefix,
            'nameSuffix' => $nameSuffix,
        ]);
    }

    private function userMayListCalendars(): bool
    {
        foreach ($this->allowedEntityTypesProvider->getEntityTypeList() as $entityType) {
            if ($this->acl->checkScope($entityType)) {
                return true;
            }
        }

        foreach ($this->dateCapableEntityTypesProvider->getEntityTypeList() as $entityType) {
            if ($this->acl->checkScope($entityType)) {
                return true;
            }
        }

        return $this->acl->checkScope('Calendar');
    }
}
