<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

/**
 * Per-user Google Calendar background sync mode (stored on ExternalAccount.data).
 */
final class SyncMode
{
    public const NONE = 'none';

    public const BIDIRECTIONAL = 'bidirectional';

    public const CRM_TO_GOOGLE = 'crmToGoogle';

    public const GOOGLE_TO_CRM = 'googleToCrm';

    public const DEFAULT = self::NONE;

    /** @var list<string> */
    public const ALL = [
        self::NONE,
        self::BIDIRECTIONAL,
        self::CRM_TO_GOOGLE,
        self::GOOGLE_TO_CRM,
    ];

    public static function isValid(?string $value): bool
    {
        return $value !== null && in_array($value, self::ALL, true);
    }
}
