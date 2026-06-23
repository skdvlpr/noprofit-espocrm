<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

/**
 * Canonical CalendarDateSource seed rows for universal (non-vertical) CRM entities.
 */
final class CalendarDateSourceDefaults
{
    /** @var array<string, string> entityType:sourceDateType => label */
    public const CANONICAL_LABELS = [
        'Meeting:main' => 'Meeting',
        'Call:main' => 'Call',
        'Task:main' => 'Task',
        'Task:dateStart' => 'Start',
        'Opportunity:closeDate' => 'Close',
        'Campaign:campaignFinDate' => 'Campaign',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function sources(): array
    {
        return [
            [
                'name' => 'Meeting start date',
                'targetEntityType' => 'Meeting',
                'dateField' => 'dateStart',
                'endDateField' => 'dateEnd',
                'sourceDateType' => 'main',
                'label' => self::CANONICAL_LABELS['Meeting:main'],
                'allDay' => false,
                'sortOrder' => 10,
            ],
            [
                'name' => 'Call start date',
                'targetEntityType' => 'Call',
                'dateField' => 'dateStart',
                'endDateField' => 'dateEnd',
                'sourceDateType' => 'main',
                'label' => self::CANONICAL_LABELS['Call:main'],
                'allDay' => false,
                'sortOrder' => 20,
            ],
            [
                'name' => 'Task due date',
                'targetEntityType' => 'Task',
                'dateField' => 'dateEnd',
                'endDateField' => null,
                'sourceDateType' => 'main',
                'label' => self::CANONICAL_LABELS['Task:main'],
                'allDay' => true,
                'sortOrder' => 30,
            ],
            [
                'name' => 'Task start date',
                'targetEntityType' => 'Task',
                'dateField' => 'dateStart',
                'endDateField' => null,
                'sourceDateType' => 'dateStart',
                'label' => self::CANONICAL_LABELS['Task:dateStart'],
                'allDay' => false,
                'sortOrder' => 31,
            ],
            [
                'name' => 'Opportunity close date',
                'targetEntityType' => 'Opportunity',
                'dateField' => 'closeDate',
                'endDateField' => null,
                'sourceDateType' => 'closeDate',
                'label' => self::CANONICAL_LABELS['Opportunity:closeDate'],
                'allDay' => true,
                'sortOrder' => 50,
            ],
            [
                'name' => 'Campaign start date',
                'targetEntityType' => 'Campaign',
                'dateField' => 'startDate',
                'endDateField' => null,
                'sourceDateType' => 'campaignFinDate',
                'label' => self::CANONICAL_LABELS['Campaign:campaignFinDate'],
                'allDay' => true,
                'sortOrder' => 90,
            ],
        ];
    }

    public static function labelKey(string $entityType, string $sourceDateType): string
    {
        return $entityType . ':' . ($sourceDateType !== '' ? $sourceDateType : 'main');
    }

    public static function canonicalLabel(string $entityType, string $sourceDateType): ?string
    {
        return self::CANONICAL_LABELS[self::labelKey($entityType, $sourceDateType)] ?? null;
    }
}
