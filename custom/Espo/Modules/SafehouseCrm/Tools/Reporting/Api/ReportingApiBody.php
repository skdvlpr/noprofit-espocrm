<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting\Api;

use stdClass;

/**
 * Normalize Espo API JSON body (stdClass) to associative array for reporting actions.
 */
final class ReportingApiBody
{
    /**
     * @return array<string, mixed>
     */
    public static function toArray(?stdClass $body): array
    {
        if ($body === null) {
            return [];
        }

        $decoded = json_decode(json_encode($body), true);

        return is_array($decoded) ? $decoded : [];
    }
}
