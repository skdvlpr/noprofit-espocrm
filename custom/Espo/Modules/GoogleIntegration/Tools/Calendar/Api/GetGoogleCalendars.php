<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\Forbidden;
use Espo\Modules\GoogleIntegration\Tools\Calendar\AllowedEntityTypesProvider;
use Espo\Modules\GoogleIntegration\Tools\Calendar\DateCapableEntityTypesProvider;
use Throwable;

class GetGoogleCalendars implements Action
{
    public function __construct(
        private Acl $acl,
        private GoogleClientProvider $googleClientProvider,
        private AllowedEntityTypesProvider $allowedEntityTypesProvider,
        private DateCapableEntityTypesProvider $dateCapableEntityTypesProvider
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->userMayListCalendars()) {
            throw new Forbidden();
        }

        $list = [];

        try {
            foreach ($this->googleClientProvider->get()->listCalendars() as $item) {
                if (!is_array($item)) {
                    continue;
                }

                $id = (string) ($item['id'] ?? '');

                if ($id === '') {
                    continue;
                }

                $list[] = [
                    'id' => $id,
                    'summary' => (string) ($item['summary'] ?? $id),
                ];
            }
        } catch (Forbidden) {
            return ResponseComposer::json([
                'list' => [['id' => 'primary', 'summary' => 'primary']],
                'connected' => false,
            ]);
        } catch (Throwable) {
            return ResponseComposer::json([
                'list' => [['id' => 'primary', 'summary' => 'primary']],
                'connected' => false,
            ]);
        }

        if ($list === []) {
            $list[] = ['id' => 'primary', 'summary' => 'primary'];
        }

        return ResponseComposer::json([
            'list' => $list,
            'connected' => true,
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
