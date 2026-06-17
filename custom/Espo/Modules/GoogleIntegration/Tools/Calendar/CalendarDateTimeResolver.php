<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\Config;
use RuntimeException;

/**
 * Converts Espo UTC datetime storage to Google Calendar wall-clock + timeZone export.
 */
class CalendarDateTimeResolver
{
    private const STORAGE_FORMAT = 'Y-m-d H:i:s';

    public function __construct(
        private Config $config
    ) {}

    public function getExportTimeZone(): string
    {
        return (string) ($this->config->get('timeZone') ?? 'UTC');
    }

    /**
     * @return array{start: array<string, string>, end: array<string, string>}
     */
    public function buildGoogleTimedRange(string $startUtc, string $endUtc): array
    {
        $startWall = $this->utcStorageToWallClockDateTime($startUtc);
        $endWall = $this->utcStorageToWallClockDateTime($endUtc);

        $startInstant = $this->parseUtcStorage($startUtc);
        $endInstant = $this->parseUtcStorage($endUtc);

        if ($endInstant <= $startInstant) {
            $endWall = $startInstant
                ->add(new DateInterval('PT30M'))
                ->setTimezone(new DateTimeZone($this->getExportTimeZone()))
                ->format('Y-m-d\TH:i:s');
        }

        $timeZone = $this->getExportTimeZone();

        return [
            'start' => [
                'dateTime' => $startWall,
                'timeZone' => $timeZone,
            ],
            'end' => [
                'dateTime' => $endWall,
                'timeZone' => $timeZone,
            ],
        ];
    }

    public function utcStorageToWallClockDateTime(string $utcDateTime): string
    {
        return $this->parseUtcStorage($utcDateTime)
            ->setTimezone(new DateTimeZone($this->getExportTimeZone()))
            ->format('Y-m-d\TH:i:s');
    }

    private function parseUtcStorage(string $utcDateTime): DateTimeImmutable
    {
        $value = trim($utcDateTime);

        if (strlen($value) === 16) {
            $value .= ':00';
        }

        $parsed = DateTimeImmutable::createFromFormat(self::STORAGE_FORMAT, $value, new DateTimeZone('UTC'));

        if ($parsed === false) {
            throw new RuntimeException('Could not parse UTC datetime storage `' . $utcDateTime . '`.');
        }

        return $parsed;
    }
}
