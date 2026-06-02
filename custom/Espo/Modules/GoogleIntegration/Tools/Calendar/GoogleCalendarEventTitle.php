<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

/**
 * Canonical Google Calendar / CRM calendar event title: "{record name} - {date source label}".
 */
final class GoogleCalendarEventTitle
{
    public const SEPARATOR = ' - ';

    public static function format(string $recordName, string $dateSourceLabel): string
    {
        $base = trim($recordName);
        $label = trim($dateSourceLabel);

        if ($base === '') {
            return $label;
        }

        if ($label === '') {
            return $base;
        }

        return $base . self::SEPARATOR . $label;
    }
}
