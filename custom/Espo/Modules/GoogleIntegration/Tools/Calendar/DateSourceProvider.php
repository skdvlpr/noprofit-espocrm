<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Acl;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

class DateSourceProvider
{
    public function __construct(
        private EntityManager $entityManager,
        private Acl $acl
    ) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getActiveSourcesForEntityType(string $entityType): array
    {
        return $this->normalizeSourceList(
            $this->entityManager
                ->getRDBRepository('CalendarDateSource')
                ->where([
                    'targetEntityType' => $entityType,
                    'isActive' => true,
                    'deleted' => false,
                ])
                ->order('sortOrder')
                ->find()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getCalendarViewSources(): array
    {
        return $this->normalizeSourceList(
            $this->entityManager
                ->getRDBRepository('CalendarDateSource')
                ->where([
                    'isActive' => true,
                    'calendarViewEnabled' => true,
                    'deleted' => false,
                ])
                ->order('sortOrder')
                ->find()
        );
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getAvailableSourcesForRecord(Entity $record): array
    {
        $sources = [];

        foreach ($this->getActiveSourcesForEntityType($record->getEntityType()) as $source) {
            $dateField = (string) ($source['dateField'] ?? '');

            if ($dateField === '' || !$record->get($dateField)) {
                continue;
            }

            $sources[] = $source;
        }

        return $sources;
    }

    /**
     * Canonical sourceDateType for link lookup and persistence.
     * Maps legacy empty/main keys to the first active source when they are not in the allowed list.
     */
    public function canonicalSourceDateType(string $entityType, string $sourceDateType): string
    {
        $sources = $this->getActiveSourcesForEntityType($entityType);

        if ($sources === []) {
            return $sourceDateType !== '' ? $sourceDateType : 'main';
        }

        $allowed = array_values(array_filter(array_map(
            static fn (array $source): string => (string) ($source['sourceDateType'] ?? 'main'),
            $sources
        )));

        if (in_array($sourceDateType, $allowed, true)) {
            return $sourceDateType;
        }

        if ($sourceDateType === '' || $sourceDateType === 'main') {
            return $allowed[0];
        }

        return $sourceDateType;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getReadableTemplates(string $entityType): array
    {
        if (!$this->acl->checkScope($entityType)) {
            return [];
        }

        $templates = $this->entityManager
            ->getRDBRepository('CalendarTemplate')
            ->where([
                'targetEntityType' => $entityType,
                'isActive' => true,
                'deleted' => false,
            ])
            ->order('name')
            ->find();

        $list = [];

        foreach ($templates as $template) {
            $list[] = [
                'id' => $template->getId(),
                'name' => $template->get('name'),
                'targetEntityType' => $template->get('targetEntityType'),
                'colorId' => $template->get('colorId') ?? '',
                'reminderMode' => $template->get('reminderMode') ?? 'none',
            ];
        }

        return $list;
    }

    /**
     * @param iterable<Entity> $entities
     * @return array<int, array<string, mixed>>
     */
    private function normalizeSourceList(iterable $entities): array
    {
        $list = [];

        foreach ($entities as $entity) {
            $list[] = [
                'id' => $entity->getId(),
                'name' => $entity->get('name'),
                'targetEntityType' => $entity->get('targetEntityType'),
                'dateField' => $entity->get('dateField'),
                'endDateField' => $entity->get('endDateField'),
                'sourceDateType' => $entity->get('sourceDateType') ?: 'main',
                'label' => $entity->get('label') ?: $entity->get('name'),
                'allDay' => (bool) $entity->get('allDay'),
                'calendarViewEnabled' => (bool) $entity->get('calendarViewEnabled'),
                'defaultTemplateId' => $entity->get('defaultTemplateId'),
                'sortOrder' => (int) ($entity->get('sortOrder') ?? 0),
            ];
        }

        return $list;
    }
}
