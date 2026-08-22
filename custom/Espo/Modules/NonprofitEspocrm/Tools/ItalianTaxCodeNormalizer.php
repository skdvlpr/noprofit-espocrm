<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

/**
 * Canonical uppercase form for Italian Codice Fiscale / Partita IVA stored in taxCode.
 */
class ItalianTaxCodeNormalizer
{
    public static function normalize(mixed $value): mixed
    {
        if (!is_string($value) || $value === '') {
            return $value;
        }

        return mb_strtoupper(trim($value));
    }
}
