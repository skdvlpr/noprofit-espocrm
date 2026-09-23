<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

/**
 * Lead tax code is stored for staff to see. It is not the Contact fiscal-code check.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 */
class LeadTaxCode
{
    public static function normalize(mixed $value): ?string
    {
        if (!is_string($value)) {
            return null;
        }

        $value = strtoupper(trim($value));

        if ($value === '') {
            return null;
        }

        if (preg_match('/^[A-Z0-9 ]+$/', $value) === 1) {
            $value = str_replace(' ', '', $value);
        }

        return $value === '' ? null : $value;
    }
}
