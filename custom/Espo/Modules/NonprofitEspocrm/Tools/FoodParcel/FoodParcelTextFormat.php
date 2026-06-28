<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\FoodParcel;

/**
 * Normalizes free-text fields for HTML-based PDF templates.
 */
class FoodParcelTextFormat
{
    public static function formatNotesPdf(mixed $notes): string
    {
        if ($notes === null) {
            return '';
        }

        $text = self::normalizePlainText((string) $notes);

        if ($text === '') {
            return '';
        }

        $html = '';

        foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
            $line = trim($line);

            if ($line === '') {
                $html .= '<div style="margin:0;padding:0;height:8px;line-height:0;font-size:0;"></div>';

                continue;
            }

            $html .= '<div style="margin:0;padding:0;line-height:1.35;">'
                . htmlspecialchars($line, ENT_QUOTES | ENT_HTML5, 'UTF-8')
                . '</div>';
        }

        return $html;
    }

    private static function normalizePlainText(string $text): string
    {
        $text = preg_replace('/<br\s*\/?>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/p>\s*<p[^>]*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/?p[^>]*>/i', "\n", $text) ?? $text;
        $text = preg_replace('/<\/?div[^>]*>/i', "\n", $text) ?? $text;
        $text = html_entity_decode(strip_tags($text), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace("/[ \t]+/u", ' ', $text) ?? $text;
        $text = preg_replace("/\n{3,}/", "\n\n", $text) ?? $text;

        return trim($text);
    }
}
