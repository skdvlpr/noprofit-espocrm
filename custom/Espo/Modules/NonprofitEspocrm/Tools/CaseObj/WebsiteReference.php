<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\CaseObj;

/**
 * Case intake reference IDs — minted only in CRM (immutable after create).
 *
 * Prefix by tipo segnalazione / inbox-resolved type:
 *   SportelloDigitale → sd-
 *   SportelloLegale   → sl-
 *   RichiestaGenerica → rg-
 *   everything else   → sh-
 */
class WebsiteReference
{
    /** @var array<string, string> */
    public const PREFIX_BY_TYPE = [
        'SportelloDigitale' => 'sd',
        'SportelloLegale' => 'sl',
        'RichiestaGenerica' => 'rg',
    ];

    public const DEFAULT_PREFIX = 'sh';

    public static function prefixForType(string $type): string
    {
        $type = trim($type);

        if ($type === '') {
            return self::DEFAULT_PREFIX;
        }

        return self::PREFIX_BY_TYPE[$type] ?? self::DEFAULT_PREFIX;
    }

    public static function build(string $prefix, string $token): string
    {
        return strtolower(trim($prefix)).'-'.strtolower(trim($token));
    }

    /**
     * Correlation token from website form emails (not the CRM Case ID).
     * Kept for Lead↔Case linking; CRM still mints websiteReferenceId separately.
     */
    public static function extractCorrelationToken(string $source): ?string
    {
        if ($source === '') {
            return null;
        }

        // Explicit correlation line from site.
        if (preg_match('/^Correlation:\s*([a-z0-9-]{8,})$/mi', $source, $matches) === 1) {
            return strtolower(trim($matches[1]));
        }

        // Site bracketed correlation: [corr-uuid]
        if (preg_match('/\[corr-([a-z0-9-]+)\]/i', $source, $matches) === 1) {
            return strtolower($matches[1]);
        }

        // Legacy bracketed tokens in subject/body (sd-|sl-|rg-|sh-uuid).
        if (preg_match('/\[(?:sh|sd|sl|rg)-([a-z0-9-]+)\]/i', $source, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    /** @deprecated Use extractCorrelationToken — CRM IDs are not parsed from mail. */
    public static function extractFromText(string $source): ?string
    {
        if ($source === '') {
            return null;
        }

        if (preg_match('/\[((?:sh|sd|sl|rg)-[a-z0-9-]+)\]/i', $source, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    public static function mintForType(string $type): string
    {
        return self::build(self::prefixForType($type), self::uuidV4());
    }

    private static function uuidV4(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);

        $hex = bin2hex($bytes);

        return substr($hex, 0, 8).'-'.
            substr($hex, 8, 4).'-'.
            substr($hex, 12, 4).'-'.
            substr($hex, 16, 4).'-'.
            substr($hex, 20, 12);
    }
}
