<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

/**
 * Canonical per-date Google Calendar field and layout definitions (dynamic provisioning).
 */
class GoogleCalendarCapableEntities
{
    /**
     * @return array<string, object>
     */
    public static function perDateFieldDefs(): array
    {
        return [
            'saveToGoogleCalendar' => (object) [
                'type' => 'bool',
                'default' => false,
                'audited' => true,
            ],
            'googleCalendarDateSourceList' => (object) [
                'type' => 'multiEnum',
                'view' => 'google-integration:views/fields/google-calendar-date-source-list',
                'audited' => true,
            ],
            'googleCalendarEventSettings' => (object) [
                'type' => 'jsonArray',
                'view' => 'google-integration:views/fields/google-calendar-opportunity-event-settings',
                'audited' => true,
            ],
            'googleCalendarId' => (object) [
                'type' => 'enum',
                'options' => ['primary'],
                'default' => 'primary',
                'view' => 'google-integration:views/fields/google-calendar-id',
            ],
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public static function googleCalendarDetailPanelRows(): array
    {
        return [
            [
                ['name' => 'saveToGoogleCalendar'],
                ['name' => 'googleCalendarDateSourceList'],
            ],
            [
                ['name' => 'googleCalendarEventSettings', 'fullWidth' => true],
            ],
        ];
    }

    /**
     * Full i18n slice merged into every calendar-capable entity (fields, labels, options).
     *
     * @return array<string, array{fields: array<string, string>, labels: array<string, string>, options: array<string, array<string, string>>}>
     */
    public static function i18nBundleByLanguage(): array
    {
        return [
            'en_US' => [
                'fields' => [
                    'saveToGoogleCalendar' => 'Save in Google Calendar',
                    'googleCalendarDateSourceList' => 'Google Calendar Dates',
                    'googleCalendarEventSettings' => 'Google event settings by date',
                    'googleCalendarId' => 'Google Calendar',
                    'googleCalendarTemplate' => 'Calendar template',
                    'googleCalendarReminderMode' => 'Google reminder mode',
                    'googleCalendarReminders' => 'Google reminders',
                    'googleCalendarDescriptionTemplateOverride' => 'Event description',
                    'googleCalendarLocation' => 'Location',
                    'googleCalendarVisibility' => 'Visibility',
                    'googleCalendarTransparency' => 'Availability',
                    'googleCalendarColorId' => 'Color',
                ],
                'labels' => [
                    'Google Calendar' => 'Google Calendar',
                    'googleCalendarPerDateSettingsHelp' => 'Configure Google Calendar options separately for each selected date.',
                    'googleCalendarTemplateSelectHelp' => 'Choose a template to fill the fields below. You can edit any value before saving.',
                    'googleCalendarReminderNotice' => 'You can save this record to Google Calendar with or without Google reminders.',
                    'googleCalendarTemplateVariables' => 'Insert record field',
                    'googleCalendarTemplateVariableSearch' => 'Search fields',
                    'googleCalendarCurrentRecordFields' => 'Current record fields',
                    'googleCalendarRelatedRecordFields' => 'Related record fields',
                    'googleCalendarNoVariables' => 'No fields match the search.',
                    'googleCalendarDateMain' => 'Start date',
                    'googleCalendarDateEnd' => 'End date',
                    'googleCalendarDatePresentation' => 'Presentation date',
                    'googleCalendarDateClose' => 'Close date',
                    'googleCalendarTemplateLoadFailed' => 'Could not load calendar templates. Run Rebuild or reinstall Google Integration.',
                ],
                'options' => self::sharedOptionsEn(),
            ],
            'it_IT' => [
                'fields' => [
                    'saveToGoogleCalendar' => 'Salva in Google Calendar',
                    'googleCalendarDateSourceList' => 'Date Google Calendar',
                    'googleCalendarEventSettings' => 'Impostazioni evento Google per data',
                    'googleCalendarId' => 'Calendario Google',
                    'googleCalendarTemplate' => 'Template calendario',
                    'googleCalendarReminderMode' => 'Modalità promemoria Google',
                    'googleCalendarReminders' => 'Promemoria Google',
                    'googleCalendarDescriptionTemplateOverride' => 'Descrizione evento',
                    'googleCalendarLocation' => 'Luogo',
                    'googleCalendarVisibility' => 'Visibilità',
                    'googleCalendarTransparency' => 'Disponibilità',
                    'googleCalendarColorId' => 'Colore',
                ],
                'labels' => [
                    'Google Calendar' => 'Google Calendar',
                    'googleCalendarPerDateSettingsHelp' => 'Configura separatamente le opzioni Google Calendar per ogni data selezionata.',
                    'googleCalendarTemplateSelectHelp' => 'Scegli un template per compilare i campi sotto. Puoi modificarli prima di salvare.',
                    'googleCalendarReminderNotice' => 'Puoi salvare questo record in Google Calendar con o senza promemoria Google.',
                    'googleCalendarTemplateVariables' => 'Inserisci campo del record',
                    'googleCalendarTemplateVariableSearch' => 'Cerca campi',
                    'googleCalendarCurrentRecordFields' => 'Campi del record corrente',
                    'googleCalendarRelatedRecordFields' => 'Campi dei record collegati',
                    'googleCalendarNoVariables' => 'Nessun campo corrisponde alla ricerca.',
                    'googleCalendarDateMain' => 'Data inizio',
                    'googleCalendarDateEnd' => 'Data fine',
                    'googleCalendarDatePresentation' => 'Data presentazione',
                    'googleCalendarDateClose' => 'Data chiusura',
                    'googleCalendarTemplateLoadFailed' => 'Impossibile caricare i template calendario. Esegui Rebuild o reinstalla Google Integration.',
                ],
                'options' => self::sharedOptionsIt(),
            ],
            'ru_RU' => [
                'fields' => [
                    'saveToGoogleCalendar' => 'Сохранить в Google Calendar',
                    'googleCalendarDateSourceList' => 'Даты Google Calendar',
                    'googleCalendarEventSettings' => 'Настройки событий Google по датам',
                    'googleCalendarId' => 'Календарь Google',
                    'googleCalendarTemplate' => 'Шаблон календаря',
                    'googleCalendarReminderMode' => 'Режим напоминаний Google',
                    'googleCalendarReminders' => 'Напоминания Google',
                    'googleCalendarDescriptionTemplateOverride' => 'Описание события',
                    'googleCalendarLocation' => 'Место',
                    'googleCalendarVisibility' => 'Видимость',
                    'googleCalendarTransparency' => 'Занятость',
                    'googleCalendarColorId' => 'Цвет',
                ],
                'labels' => [
                    'Google Calendar' => 'Google Calendar',
                    'googleCalendarPerDateSettingsHelp' => 'Настраивайте параметры Google Calendar отдельно для каждой выбранной даты.',
                    'googleCalendarTemplateSelectHelp' => 'Выберите шаблон — поля ниже заполнятся автоматически. Перед сохранением их можно изменить.',
                    'googleCalendarReminderNotice' => 'Можно сохранить запись в Google Calendar с напоминаниями или без них.',
                    'googleCalendarTemplateVariables' => 'Вставить поле записи',
                    'googleCalendarTemplateVariableSearch' => 'Поиск полей',
                    'googleCalendarCurrentRecordFields' => 'Поля текущей записи',
                    'googleCalendarRelatedRecordFields' => 'Поля связанных записей',
                    'googleCalendarNoVariables' => 'Переменные не найдены',
                    'googleCalendarDateMain' => 'Дата начала',
                    'googleCalendarDateEnd' => 'Дата окончания',
                    'googleCalendarDatePresentation' => 'Дата подачи',
                    'googleCalendarDateClose' => 'Дата закрытия',
                    'googleCalendarTemplateLoadFailed' => 'Не удалось загрузить шаблоны календаря. Выполните Rebuild.',
                ],
                'options' => self::sharedOptionsRu(),
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function sharedOptionsEn(): array
    {
        return [
            'googleCalendarReminderMode' => [
                'none' => 'No reminder',
                'default' => 'Use calendar defaults',
                'custom' => 'Custom reminders',
            ],
            'googleCalendarReminders' => [
                'popup' => 'Notification',
                'email' => 'Email',
                'minutes' => 'minutes before',
                'hours' => 'hours before',
                'days' => 'days before',
                'weeks' => 'weeks before',
            ],
            'googleCalendarVisibility' => [
                'default' => 'Default',
                'private' => 'Private',
                'public' => 'Public',
            ],
            'googleCalendarTransparency' => [
                'opaque' => 'Busy',
                'transparent' => 'Available',
            ],
            'googleCalendarColorId' => [
                '' => 'Default',
                '1' => 'Lavender',
                '2' => 'Sage',
                '3' => 'Grape',
                '4' => 'Flamingo',
                '5' => 'Banana',
                '6' => 'Tangerine',
                '7' => 'Peacock',
                '8' => 'Graphite',
                '9' => 'Blueberry',
                '10' => 'Basil',
                '11' => 'Tomato',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function sharedOptionsIt(): array
    {
        return [
            'googleCalendarReminderMode' => [
                'none' => 'Nessun promemoria',
                'default' => 'Usa predefiniti del calendario',
                'custom' => 'Promemoria personalizzati',
            ],
            'googleCalendarReminders' => [
                'popup' => 'Notifica',
                'email' => 'Email',
                'minutes' => 'minuti prima',
                'hours' => 'ore prima',
                'days' => 'giorni prima',
                'weeks' => 'settimane prima',
            ],
            'googleCalendarVisibility' => [
                'default' => 'Predefinita',
                'private' => 'Privata',
                'public' => 'Pubblica',
            ],
            'googleCalendarTransparency' => [
                'opaque' => 'Occupato',
                'transparent' => 'Disponibile',
            ],
            'googleCalendarColorId' => [
                '' => 'Predefinito',
                '1' => 'Lavanda',
                '2' => 'Salvia',
                '3' => 'Uva',
                '4' => 'Fenicottero',
                '5' => 'Banana',
                '6' => 'Mandarino',
                '7' => 'Pavone',
                '8' => 'Grafite',
                '9' => 'Mirtillo',
                '10' => 'Basilico',
                '11' => 'Pomodoro',
            ],
        ];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private static function sharedOptionsRu(): array
    {
        return [
            'googleCalendarReminderMode' => [
                'none' => 'Без напоминания',
                'default' => 'По умолчанию календаря',
                'custom' => 'Свои напоминания',
            ],
            'googleCalendarReminders' => [
                'popup' => 'Уведомление',
                'email' => 'Email',
                'minutes' => 'минут до',
                'hours' => 'часов до',
                'days' => 'дней до',
                'weeks' => 'недель до',
            ],
            'googleCalendarVisibility' => [
                'default' => 'По умолчанию',
                'private' => 'Приватная',
                'public' => 'Публичная',
            ],
            'googleCalendarTransparency' => [
                'opaque' => 'Занят',
                'transparent' => 'Свободен',
            ],
            'googleCalendarColorId' => [
                '' => 'По умолчанию',
                '1' => 'Лаванда',
                '2' => 'Шалфей',
                '3' => 'Виноград',
                '4' => 'Фламинго',
                '5' => 'Банан',
                '6' => 'Мандарин',
                '7' => 'Павлин',
                '8' => 'Графит',
                '9' => 'Черника',
                '10' => 'Базилик',
                '11' => 'Томат',
            ],
        ];
    }
}
