<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

/**
 * Google Calendar API expects plain text in summary/description/location (not HTML entities).
 */
final class GoogleCalendarPlainText
{
    public static function normalize(string $text): string
    {
        if ($text === '') {
            return '';
        }

        return html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
