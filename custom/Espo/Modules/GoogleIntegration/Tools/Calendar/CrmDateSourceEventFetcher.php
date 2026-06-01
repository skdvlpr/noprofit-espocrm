<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Acl;
use Espo\Core\Field\DateTime;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Modules\Crm\Tools\Calendar\Items\Event;
use Espo\ORM\EntityManager;

class CrmDateSourceEventFetcher
{
    public function __construct(
        private DateSourceProvider $dateSourceProvider,
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Acl $acl,
        private CalendarDisplayDateResolver $calendarDisplayDateResolver
    ) {}

    /**
     * @param string[]|null $scopeList
     * @return Event[]
     */
    public function fetch(string $from, string $to, ?array $scopeList = null): array
    {
        $eventList = [];

        foreach ($this->dateSourceProvider->getCalendarViewSources() as $source) {
            $entityType = (string) ($source['targetEntityType'] ?? '');

            if ($entityType === '') {
                continue;
            }

            if ($scopeList !== null && !in_array($entityType, $scopeList, true)) {
                continue;
            }

            if (!$this->acl->checkScope($entityType)) {
                continue;
            }

            $dateField = (string) ($source['dateField'] ?? '');

            if ($dateField === '') {
                continue;
            }

            $eventList = array_merge(
                $eventList,
                $this->fetchForSource($entityType, $source, $from, $to)
            );
        }

        return $eventList;
    }

    /**
     * @param array<string, mixed> $source
     * @return Event[]
     */
    private function fetchForSource(string $entityType, array $source, string $from, string $to): array
    {
        $dateField = (string) $source['dateField'];
        $endDateField = (string) ($source['endDateField'] ?? '');
        $sourceDateType = (string) ($source['sourceDateType'] ?? 'main');
        $sourceLabel = (string) ($source['label'] ?? $source['name'] ?? $sourceDateType);
        $allDay = (bool) ($source['allDay'] ?? false);

        $seed = $this->entityManager->getNewEntity($entityType);
        $fieldType = $seed->getAttributeType($dateField);

        if (
            $fieldType !== FieldType::DATE
            && $fieldType !== FieldType::DATETIME
            && $fieldType !== FieldType::DATETIME_OPTIONAL
        ) {
            return [];
        }

        $fromValue = $fieldType === 'date' ? substr($from, 0, 10) : $from;
        $toValue = $fieldType === 'date' ? substr($to, 0, 10) : $to;

        $select = ['id', 'name', $dateField];

        if ($endDateField !== '' && $seed->hasAttribute($endDateField)) {
            $select[] = $endDateField;
        }

        if ($seed->hasAttribute('status')) {
            $select[] = 'status';
        }

        if ($seed->hasAttribute('stage')) {
            $select[] = 'stage';
        }

        $query = $this->selectBuilderFactory
            ->create()
            ->from($entityType)
            ->withStrictAccessControl()
            ->buildQueryBuilder()
            ->select($select)
            ->where([
                $dateField . '>=' => $fromValue,
                $dateField . '<=' => $toValue,
            ])
            ->build();

        $collection = $this->entityManager
            ->getRDBRepository($entityType)
            ->find($query);

        $eventList = [];

        foreach ($collection as $entity) {
            $dateValue = $entity->get($dateField);

            if (!$dateValue) {
                continue;
            }

            $name = (string) ($entity->get('name') ?? '');
            $title = $name !== '' ? $name : $sourceLabel;

            if ($sourceDateType !== 'main') {
                $title = $title . ' · ' . $sourceLabel;
            }

            $status = $entity->get('status') ?? $entity->get('stage');

            $presentAsAllDay = $fieldType === FieldType::DATE
                || $allDay
                || $this->calendarDisplayDateResolver->isDateTimeOptionalAllDay($entity, $dateField);

            if ($presentAsAllDay) {
                $dateString = $this->calendarDisplayDateResolver->resolveDateOnly($entity, $dateField);

                if ($dateString === null) {
                    continue;
                }

                $endDateString = $dateString;

                if ($endDateField !== '') {
                    $resolvedEnd = $this->calendarDisplayDateResolver->resolveDateOnly($entity, $endDateField);

                    if ($resolvedEnd !== null) {
                        $endDateString = $resolvedEnd;
                    }
                }

                $event = new Event(null, null, $entityType, [
                    'id' => $entity->getId(),
                    'calendarEventKey' => $entity->getId() . ':' . $sourceDateType,
                    'name' => $title,
                    'dateStartDate' => $dateString,
                    'dateEndDate' => $endDateString,
                    'status' => $status,
                ]);
            } else {
                $start = DateTime::fromString((string) $dateValue);
                $end = $start;

                if ($endDateField !== '' && $entity->get($endDateField)) {
                    $end = DateTime::fromString((string) $entity->get($endDateField));
                }

                $event = new Event($start, $end, $entityType, [
                    'id' => $entity->getId(),
                    'calendarEventKey' => $entity->getId() . ':' . $sourceDateType,
                    'name' => $title,
                    'status' => $status,
                ]);
            }

            $eventList[] = $event;
        }

        return $eventList;
    }
}
