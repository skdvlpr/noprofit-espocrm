<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarProvisioner;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateSourceProvider;

class GetDateSourceTypeOptions implements Action
{
    public function __construct(
        private DateSourceProvider $dateSourceProvider,
        private CalendarProvisioner $calendarProvisioner,
        private Acl $acl
    ) {}

    public function process(Request $request): Response
    {
        $entityType = $request->getRouteParam('entityType') ?? throw new BadRequest();

        if (!$this->acl->checkScope($entityType)) {
            throw new Forbidden();
        }

        $sources = [];

        foreach ($this->dateSourceProvider->getActiveSourcesForEntityType($entityType) as $source) {
            $sources[] = [
                'sourceDateType' => (string) ($source['sourceDateType'] ?? 'main'),
                'label' => (string) ($source['label'] ?? $source['name'] ?? ''),
                'dateField' => (string) ($source['dateField'] ?? ''),
                'calendarRoutingMode' => (string) ($source['calendarRoutingMode'] ?? 'primary'),
                'dedicatedCalendarName' => is_string($source['dedicatedCalendarName'] ?? null)
                    ? trim((string) $source['dedicatedCalendarName'])
                    : '',
                'resolvedDedicatedCalendarName' => $this->calendarProvisioner
                    ->resolveDedicatedCalendarName($source),
                'entityLabel' => $this->calendarProvisioner->resolveEntityLabel($entityType),
            ];
        }

        return ResponseComposer::json([
            'sources' => $sources,
        ]);
    }
}
