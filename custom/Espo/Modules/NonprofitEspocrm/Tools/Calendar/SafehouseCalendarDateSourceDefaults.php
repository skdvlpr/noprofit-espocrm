<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Calendar;

/**
 * Safehouse-specific CalendarDateSource seeds (requires SafehouseCrm entities/fields).
 *
 * VolunteerEmployee / Member CDS seeds removed — people live on Contact STI;
 * those entities are retired and must not be registered for Google calendar.
 */
final class SafehouseCalendarDateSourceDefaults
{
    /** @var array<string, string> */
    public const CANONICAL_LABELS = [
        'Opportunity:presentationDate' => 'Presentation',
        'ActivityOfferSlot:main' => 'Shift',
    ];

    /** @return array<int, array<string, mixed>> */
    public static function sources(): array
    {
        return [
            [
                'name' => 'Opportunity presentation date',
                'targetEntityType' => 'Opportunity',
                'dateField' => 'presentationDate',
                'endDateField' => null,
                'sourceDateType' => 'presentationDate',
                'label' => self::CANONICAL_LABELS['Opportunity:presentationDate'],
                'allDay' => true,
                'sortOrder' => 40,
            ],
            [
                'name' => 'Shift planner — slot',
                'targetEntityType' => 'ActivityOfferSlot',
                'dateField' => 'dateStart',
                'endDateField' => 'dateEnd',
                'sourceDateType' => 'main',
                'label' => self::CANONICAL_LABELS['ActivityOfferSlot:main'],
                'allDay' => false,
                'sortOrder' => 25,
                // CRM calendar uses native Espo (scopes.calendar + calendarEntityList).
                // CDS stays for optional Google export routing only.
                'calendarViewEnabled' => false,
            ],
        ];
    }

    /** @var array<int, array<string, mixed>> */
    public const DEFAULT_CALENDAR_TEMPLATES = [
        [
            'name' => 'Grants & Funding — default',
            'targetEntityType' => 'Opportunity',
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "Grants & Funding: {{name}}\nAccount: {{account.name}}\nAmount: {{amount}}\nPresentation: {{presentationDate}}\nClose: {{closeDate}}\n\n{{description}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
            'colorId' => '10',
        ],
    ];
}
