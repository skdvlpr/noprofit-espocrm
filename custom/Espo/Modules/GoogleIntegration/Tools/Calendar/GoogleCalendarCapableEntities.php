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
                'type' => 'varchar',
                'maxLength' => 255,
                'default' => 'primary',
                'view' => 'google-integration:views/fields/google-calendar-id',
            ],
            'googleCalendarShareUsers' => (object) [
                'type' => 'linkMultiple',
                'view' => 'google-integration:views/fields/google-calendar-share-users',
                'audited' => false,
            ],
            'googleCalendarShareTeams' => (object) [
                'type' => 'linkMultiple',
                'view' => 'google-integration:views/fields/google-calendar-share-teams',
                'audited' => false,
            ],
            'googleCalendarShareRoutingMode' => (object) [
                'type' => 'enum',
                'options' => [
                    CalendarRoutingMode::PRIMARY,
                    CalendarRoutingMode::AUTO_DEDICATED,
                    CalendarRoutingMode::USER_PICK,
                ],
                'default' => CalendarRoutingMode::PRIMARY,
                'audited' => true,
            ],
            'googleCalendarShareCalendarUserId' => (object) [
                'type' => 'varchar',
                'maxLength' => 17,
                'view' => 'google-integration:views/fields/google-calendar-share-calendar-user',
            ],
            'googleCalendarShareCalendarId' => (object) [
                'type' => 'varchar',
                'maxLength' => 255,
                'default' => 'primary',
                'view' => 'google-integration:views/fields/google-calendar-share-calendar-id',
            ],
        ];
    }

    /**
     * @return array<string, object>
     */
    public static function perDateLinkDefs(): array
    {
        return [
            'googleCalendarShareUsers' => (object) [
                'type' => 'hasMany',
                'entity' => 'User',
                'relationName' => 'googleCalendarShareUser',
                'layoutRelationshipsDisabled' => true,
            ],
            'googleCalendarShareTeams' => (object) [
                'type' => 'hasMany',
                'entity' => 'Team',
                'relationName' => 'googleCalendarShareTeam',
                'layoutRelationshipsDisabled' => true,
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
                ['name' => 'googleCalendarId'],
                false,
            ],
            [
                ['name' => 'googleCalendarShareUsers'],
                ['name' => 'googleCalendarShareTeams'],
            ],
            [
                ['name' => 'googleCalendarShareRoutingMode'],
                ['name' => 'googleCalendarShareCalendarUserId'],
            ],
            [
                ['name' => 'googleCalendarShareCalendarId'],
                false,
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
                    'googleCalendarShareUsers' => 'Also add to users',
                    'googleCalendarShareTeams' => 'Also add to teams',
                    'googleCalendarShareRoutingMode' => 'Calendar for shared users',
                    'googleCalendarShareCalendarUserId' => 'Shared calendar owner',
                    'googleCalendarShareCalendarId' => 'Shared Google calendar',
                ],
                'labels' => [
                    'Google Calendar' => 'Google Calendar',
                    'googleCalendarPerDateSettingsHelp' => 'Configure Google Calendar options separately for each selected date.',
                    'googleCalendarTemplateSelectHelp' => 'Choose a template to fill the fields below. You can edit any value before saving.',
                    'googleCalendarReminderNotice' => 'You can save this record to Google Calendar with or without Google reminders.',
                    'googleCalendarShareHelp' => 'Optional. Adds the same event to calendars of selected users or team members who connected Google and allowed managers to write.',
                    'googleCalendarShareUsersHint' => 'Only users with a connected Google account are listed.',
                    'googleCalendarShareUsersEmpty' => 'No users have Google Calendar connected yet.',
                    'googleCalendarShareTeamsHint' => 'All teams are available. Expand a team to see who has Google connected.',
                    'googleCalendarShareTeamsSelectTitle' => 'Select teams',
                    'googleCalendarShareGoogleBadge' => 'Google',
                    'googleCalendarShareNoGoogle' => 'No Google',
                    'googleCalendarShareMembers' => 'Members',
                    'googleCalendarShareTeamsEmptySearch' => 'No teams match the search.',
                    'googleCalendarShareRoutingHelp' => 'Primary and Create CRM calendar apply to every shared target. Pick calendar uses one chosen user’s Google account (others get Primary).',
                    'googleCalendarSharePickNeedsUser' => 'Select a shared calendar owner to list their Google calendars.',
                    'googleCalendarSharePickOwnerHint' => 'Whose Google account to pick or create the calendar in (from selected users/teams).',
                    'googleCalendarSharePickOwnerEmpty' => 'No consented Google users in the selected users/teams yet.',
                    'googleCalendarRoutingHintTitle' => 'Google calendar routing (per selected date)',
                    'googleCalendarRoutingHintPrimary' => 'Events go to your Google primary calendar.',
                    'googleCalendarRoutingHintUserPick' => 'Choose a calendar below (admin enabled user pick for this date).',
                    'googleCalendarRoutingHintAutoDedicated' => 'Events go to shared entity calendar {calendarName} (when admin enabled auto-create).',
                    'googleCalendarTemplateVariables' => 'Insert record field',
                    'googleCalendarTemplateVariableSearch' => 'Search fields',
                    'googleCalendarCurrentRecordFields' => 'Current record fields',
                    'googleCalendarRelatedRecordFields' => 'Related record fields',
                    'googleCalendarExternalRecordFields' => 'External fields ({relation})',
                    'googleCalendarNoVariables' => 'No fields match the search.',
                    'googleCalendarDateMain' => 'Start date',
                    'googleCalendarDateEnd' => 'End date',
                    'googleCalendarDatePresentation' => 'Presentation date',
                    'googleCalendarDateClose' => 'Close date',
                    'googleCalendarTemplateLoadFailed' => 'Could not load calendar templates. Run Rebuild or reinstall Google Integration.',
                ],
                'options' => array_merge(self::sharedOptionsEn(), [
                    'googleCalendarShareRoutingMode' => [
                        'primary' => 'Primary calendar',
                        'auto_dedicated' => 'Create CRM calendar',
                        'user_pick' => 'Pick existing calendar',
                    ],
                ]),
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
                    'googleCalendarShareUsers' => 'Aggiungi anche per utenti',
                    'googleCalendarShareTeams' => 'Aggiungi anche per team',
                    'googleCalendarShareRoutingMode' => 'Calendario per utenti condivisi',
                    'googleCalendarShareCalendarUserId' => 'Proprietario calendario condiviso',
                    'googleCalendarShareCalendarId' => 'Calendario Google condiviso',
                ],
                'labels' => [
                    'Google Calendar' => 'Google Calendar',
                    'googleCalendarPerDateSettingsHelp' => 'Configura separatamente le opzioni Google Calendar per ogni data selezionata.',
                    'googleCalendarTemplateSelectHelp' => 'Scegli un template per compilare i campi sotto. Puoi modificarli prima di salvare.',
                    'googleCalendarReminderNotice' => 'Puoi salvare questo record in Google Calendar con o senza promemoria Google.',
                    'googleCalendarShareHelp' => 'Opzionale. Aggiunge lo stesso evento ai calendari degli utenti o membri del team selezionati che hanno collegato Google e autorizzato i manager a scrivere.',
                    'googleCalendarShareUsersHint' => 'Sono elencati solo gli utenti con un account Google collegato.',
                    'googleCalendarShareUsersEmpty' => 'Nessun utente ha ancora collegato Google Calendar.',
                    'googleCalendarShareTeamsHint' => 'Tutti i team sono disponibili. Espandi un team per vedere chi ha Google collegato.',
                    'googleCalendarShareTeamsSelectTitle' => 'Seleziona team',
                    'googleCalendarShareGoogleBadge' => 'Google',
                    'googleCalendarShareNoGoogle' => 'Nessun Google',
                    'googleCalendarShareMembers' => 'Membri',
                    'googleCalendarShareTeamsEmptySearch' => 'Nessun team corrisponde alla ricerca.',
                    'googleCalendarShareRoutingHelp' => 'Principale e Crea calendario CRM valgono per tutti i destinatari. Scegli calendario usa un solo account Google (gli altri ricevono Principale).',
                    'googleCalendarSharePickNeedsUser' => 'Seleziona il proprietario del calendario per elencare i suoi calendari Google.',
                    'googleCalendarSharePickOwnerHint' => 'In quale account Google scegliere o creare il calendario (dagli utenti/team selezionati).',
                    'googleCalendarSharePickOwnerEmpty' => 'Nessun utente con consenso Google nei utenti/team selezionati.',
                    'googleCalendarRoutingHintTitle' => 'Instradamento Google Calendar (per data selezionata)',
                    'googleCalendarRoutingHintPrimary' => 'Gli eventi vanno nel calendario principale Google.',
                    'googleCalendarRoutingHintUserPick' => 'Scegli il calendario sotto (l\'admin ha abilitato la scelta utente per questa data).',
                    'googleCalendarRoutingHintAutoDedicated' => 'Gli eventi vanno nel calendario condiviso dell\'entità {calendarName} (se l\'admin ha abilitato la creazione automatica).',
                    'googleCalendarTemplateVariables' => 'Inserisci campo del record',
                    'googleCalendarTemplateVariableSearch' => 'Cerca campi',
                    'googleCalendarCurrentRecordFields' => 'Campi del record corrente',
                    'googleCalendarRelatedRecordFields' => 'Campi dei record collegati',
                    'googleCalendarExternalRecordFields' => 'Campi esterni ({relation})',
                    'googleCalendarNoVariables' => 'Nessun campo corrisponde alla ricerca.',
                    'googleCalendarDateMain' => 'Data inizio',
                    'googleCalendarDateEnd' => 'Data fine',
                    'googleCalendarDatePresentation' => 'Data presentazione',
                    'googleCalendarDateClose' => 'Data chiusura',
                    'googleCalendarTemplateLoadFailed' => 'Impossibile caricare i template calendario. Esegui Rebuild o reinstalla Google Integration.',
                ],
                'options' => array_merge(self::sharedOptionsIt(), [
                    'googleCalendarShareRoutingMode' => [
                        'primary' => 'Calendario principale',
                        'auto_dedicated' => 'Crea calendario CRM',
                        'user_pick' => 'Scegli calendario esistente',
                    ],
                ]),
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
                    'googleCalendarShareUsers' => 'Также добавить пользователям',
                    'googleCalendarShareTeams' => 'Также добавить командам',
                    'googleCalendarShareRoutingMode' => 'Календарь для общих пользователей',
                    'googleCalendarShareCalendarUserId' => 'Владелец общего календаря',
                    'googleCalendarShareCalendarId' => 'Общий календарь Google',
                ],
                'labels' => [
                    'Google Calendar' => 'Google Calendar',
                    'googleCalendarPerDateSettingsHelp' => 'Настраивайте параметры Google Calendar отдельно для каждой выбранной даты.',
                    'googleCalendarTemplateSelectHelp' => 'Выберите шаблон — поля ниже заполнятся автоматически. Перед сохранением их можно изменить.',
                    'googleCalendarReminderNotice' => 'Можно сохранить запись в Google Calendar с напоминаниями или без них.',
                    'googleCalendarShareHelp' => 'Необязательно. Добавляет то же событие в календари выбранных пользователей или участников команд, которые подключили Google и разрешили менеджерам запись.',
                    'googleCalendarShareUsersHint' => 'В списке только пользователи с подключённым Google-аккаунтом.',
                    'googleCalendarShareUsersEmpty' => 'Пока никто не подключил Google Calendar.',
                    'googleCalendarShareTeamsHint' => 'Доступны все команды. Раскройте команду, чтобы увидеть, у кого подключён Google.',
                    'googleCalendarShareTeamsSelectTitle' => 'Выбор команд',
                    'googleCalendarShareGoogleBadge' => 'Google',
                    'googleCalendarShareNoGoogle' => 'Без Google',
                    'googleCalendarShareMembers' => 'Участники',
                    'googleCalendarShareTeamsEmptySearch' => 'Нет команд по запросу.',
                    'googleCalendarShareRoutingHelp' => 'Основной и «Создать CRM-календарь» применяются ко всем получателям. Выбор календаря — один Google-аккаунт (остальным — основной).',
                    'googleCalendarSharePickNeedsUser' => 'Выберите владельца календаря, чтобы показать его календари Google.',
                    'googleCalendarSharePickOwnerHint' => 'В чьём Google-аккаунте выбрать или создать календарь (из выбранных пользователей/команд).',
                    'googleCalendarSharePickOwnerEmpty' => 'В выбранных пользователях/командах нет пользователей с согласием на запись в Google.',
                    'googleCalendarRoutingHintTitle' => 'Маршрутизация Google Calendar (по выбранным датам)',
                    'googleCalendarRoutingHintPrimary' => 'События попадут в основной календарь Google.',
                    'googleCalendarRoutingHintUserPick' => 'Выберите календарь ниже (админ включил выбор пользователя для этой даты).',
                    'googleCalendarRoutingHintAutoDedicated' => 'События попадут в общий календарь сущности {calendarName} (если админ включил автосоздание).',
                    'googleCalendarTemplateVariables' => 'Вставить поле записи',
                    'googleCalendarTemplateVariableSearch' => 'Поиск полей',
                    'googleCalendarCurrentRecordFields' => 'Поля текущей записи',
                    'googleCalendarRelatedRecordFields' => 'Поля связанных записей',
                    'googleCalendarExternalRecordFields' => 'Внешние поля ({relation})',
                    'googleCalendarNoVariables' => 'Переменные не найдены',
                    'googleCalendarDateMain' => 'Дата начала',
                    'googleCalendarDateEnd' => 'Дата окончания',
                    'googleCalendarDatePresentation' => 'Дата подачи',
                    'googleCalendarDateClose' => 'Дата закрытия',
                    'googleCalendarTemplateLoadFailed' => 'Не удалось загрузить шаблоны календаря. Выполните Rebuild.',
                ],
                'options' => array_merge(self::sharedOptionsRu(), [
                    'googleCalendarShareRoutingMode' => [
                        'primary' => 'Основной календарь',
                        'auto_dedicated' => 'Создать CRM-календарь',
                        'user_pick' => 'Выбрать существующий календарь',
                    ],
                ]),
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
