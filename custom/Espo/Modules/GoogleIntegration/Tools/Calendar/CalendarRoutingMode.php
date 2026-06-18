<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

class CalendarRoutingMode
{
    public const PRIMARY = 'primary';
    public const USER_PICK = 'user_pick';
    public const AUTO_DEDICATED = 'auto_dedicated';

    /** @var list<string> */
    private const VALID = [
        self::PRIMARY,
        self::USER_PICK,
        self::AUTO_DEDICATED,
    ];

    public static function isValid(?string $mode): bool
    {
        return $mode !== null && $mode !== '' && in_array($mode, self::VALID, true);
    }

    public static function normalize(?string $mode): string
    {
        return self::isValid($mode) ? $mode : self::PRIMARY;
    }
}
