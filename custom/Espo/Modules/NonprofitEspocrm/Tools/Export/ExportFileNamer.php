<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config;
use Throwable;

/**
 * Safehouse export attachment base name (no extension):
 * {ddMMyyyy}-{HHmm}-Export-{EntityType}
 * e.g. 05082026-0858-Export-ActivityOfferSlot
 */
class ExportFileNamer
{
    public function __construct(private Config $config) {}

    public function buildBaseName(string $entityType): string
    {
        $timezoneName = (string) ($this->config->get('timeZone') ?: 'UTC');

        try {
            $timezone = new DateTimeZone($timezoneName);
        } catch (Throwable) {
            $timezone = new DateTimeZone('UTC');
        }

        $now = new DateTimeImmutable('now', $timezone);

        return $now->format('dmY-Hi') . '-Export-' . $entityType;
    }
}
