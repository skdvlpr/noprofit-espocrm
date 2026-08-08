<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Calendar;

/**
 * Safehouse-specific CalendarDateSource seeds (requires SafehouseCrm entities/fields).
 */
final class SafehouseCalendarDateSourceDefaults
{
    /** @var array<string, string> */
    public const CANONICAL_LABELS = [
        'Opportunity:presentationDate' => 'Presentation',
        'VolunteerEmployee:main' => 'Start',
        'VolunteerEmployee:endDate' => 'End',
        'Member:main' => 'Member',
        'ActivityOfferSlot:main' => 'Shift',
        'GCalSmokeAllDay:main' => 'All-day',
        'GCalSmokeDateTime:main' => 'DateTime',
        'GCalSmokeTwinDate:primaryDate' => 'Primary',
        'GCalSmokeTwinDate:reviewDate' => 'Review',
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
                'name' => 'Volunteer / Employee start date',
                'targetEntityType' => 'VolunteerEmployee',
                'dateField' => 'startDate',
                'endDateField' => null,
                'sourceDateType' => 'main',
                'label' => self::CANONICAL_LABELS['VolunteerEmployee:main'],
                'allDay' => true,
                'sortOrder' => 60,
            ],
            [
                'name' => 'Volunteer / Employee end date',
                'targetEntityType' => 'VolunteerEmployee',
                'dateField' => 'endDate',
                'endDateField' => null,
                'sourceDateType' => 'endDate',
                'label' => self::CANONICAL_LABELS['VolunteerEmployee:endDate'],
                'allDay' => true,
                'sortOrder' => 61,
            ],
            [
                'name' => 'Member birth date',
                'targetEntityType' => 'Member',
                'dateField' => 'birthDate',
                'endDateField' => null,
                'sourceDateType' => 'main',
                'label' => self::CANONICAL_LABELS['Member:main'],
                'allDay' => true,
                'sortOrder' => 70,
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
        [
            'name' => 'Volunteer / Employee — default',
            'targetEntityType' => 'VolunteerEmployee',
            'summaryTemplate' => '{{name}}',
            'descriptionTemplate' => "{{name}}\nStart: {{startDate}}\nEnd: {{endDate}}\n\n{{extra}}\n\nEspoCRM: {{espocrmUrl}}",
            'reminderMode' => 'none',
            'transparency' => 'opaque',
        ],
    ];
}
