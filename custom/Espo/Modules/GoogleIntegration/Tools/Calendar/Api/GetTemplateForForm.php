<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Exceptions\NotFound;
use Espo\Modules\GoogleIntegration\Tools\Calendar\CalendarTemplateApplier;
use Espo\ORM\EntityManager;

/**
 * Returns template values for per-date Google Calendar UI (raw or rendered for a record).
 */
class GetTemplateForForm implements Action
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl,
        private CalendarTemplateApplier $calendarTemplateApplier
    ) {}

    public function process(Request $request): Response
    {
        $templateId = $request->getRouteParam('templateId') ?? throw new BadRequest();
        $entityType = $request->getQueryParam('entityType');
        $entityId = $request->getQueryParam('entityId');
        $sourceDateType = $request->getQueryParam('sourceDateType') ?? 'main';

        if (!is_string($entityType) || $entityType === '') {
            throw new BadRequest('entityType is required.');
        }

        if (!is_string($sourceDateType) || $sourceDateType === '') {
            $sourceDateType = 'main';
        }

        if (!$this->acl->checkScope($entityType)) {
            throw new Forbidden();
        }

        $template = $this->entityManager->getEntityById('CalendarTemplate', $templateId);

        if ($template === null) {
            throw new NotFound();
        }

        if (!$this->acl->checkEntityRead($template)) {
            throw new Forbidden();
        }

        if ($template->get('targetEntityType') !== $entityType) {
            throw new BadRequest('Template does not match entity type.');
        }

        if (is_string($entityId) && $entityId !== '') {
            $record = $this->entityManager->getEntityById($entityType, $entityId);

            if ($record === null) {
                throw new NotFound();
            }

            if (!$this->acl->checkEntityRead($record)) {
                throw new Forbidden();
            }

            return ResponseComposer::json(
                $this->mapAppliedSettings(
                    $this->calendarTemplateApplier->apply($templateId, $record, $sourceDateType)
                )
            );
        }

        return ResponseComposer::json($this->mapRawTemplate($template));
    }

    /**
     * @return array<string, mixed>
     */
    private function mapRawTemplate(\Espo\ORM\Entity $template): array
    {
        return [
            'description' => (string) ($template->get('descriptionTemplate') ?? ''),
            'location' => (string) ($template->get('locationTemplate') ?? ''),
            'colorId' => (string) ($template->get('colorId') ?? ''),
            'visibility' => $template->get('visibility') ?: 'default',
            'transparency' => $template->get('transparency') ?: 'opaque',
            'reminderMode' => $template->get('reminderMode') ?: 'none',
            'reminders' => $template->get('reminders') ?: [],
        ];
    }

    /**
     * @param array<string, mixed> $applied
     * @return array<string, mixed>
     */
    private function mapAppliedSettings(array $applied): array
    {
        return [
            'description' => (string) ($applied['description'] ?? ''),
            'location' => (string) ($applied['location'] ?? ''),
            'colorId' => (string) ($applied['colorId'] ?? ''),
            'visibility' => $applied['visibility'] ?? 'default',
            'transparency' => $applied['transparency'] ?? 'opaque',
            'reminderMode' => $applied['reminderMode'] ?? 'none',
            'reminders' => is_array($applied['reminders'] ?? null) ? $applied['reminders'] : [],
        ];
    }
}
