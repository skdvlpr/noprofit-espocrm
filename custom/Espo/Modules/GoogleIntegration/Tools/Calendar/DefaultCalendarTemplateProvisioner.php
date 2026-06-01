<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\ORM\EntityManager;

/**
 * Ensures a default CalendarTemplate row exists for calendar-export entity types.
 */
class DefaultCalendarTemplateProvisioner
{
    public function __construct(
        private EntityManager $entityManager,
        private EntityScopeNameTranslator $entityScopeNameTranslator
    ) {}

    public function ensureForEntityType(string $entityType): void
    {
        if ($entityType === '') {
            return;
        }

        $repo = $this->entityManager->getRDBRepository('CalendarTemplate');

        $existing = $repo
            ->where([
                'targetEntityType' => $entityType,
                'deleted' => false,
            ])
            ->findOne();

        if ($existing !== null) {
            return;
        }

        $label = $this->entityScopeNameTranslator->translate($entityType) ?? $entityType;

        $this->entityManager->saveEntity($this->entityManager->createEntity('CalendarTemplate', [
            'name' => $label . ' — default',
            'targetEntityType' => $entityType,
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "{{name}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
            'isActive' => true,
        ]));
    }
}
