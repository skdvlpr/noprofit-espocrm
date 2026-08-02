<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\ORM\Entity;

/**
 * Builds a 5-field cron expression from visual schedule fields (Vtiger-like presets).
 */
class ScheduleBuilder
{
    public function applyToEntity(Entity $definition): void
    {
        if ((string) $definition->get('triggerType') !== 'scheduled') {
            return;
        }

        $preset = (string) ($definition->get('schedulePreset') ?? 'daily');

        if ($preset === 'cron') {
            return;
        }

        $cron = $this->build(
            $preset,
            (string) ($definition->get('scheduleMinute') ?? '0'),
            (string) ($definition->get('scheduleHour') ?? '9'),
            $definition->get('scheduleWeekdays'),
            (string) ($definition->get('scheduleMonthDay') ?? '1'),
        );

        if ($cron !== null) {
            $definition->set('scheduling', $cron);
        }
    }

    /**
     * @param mixed $weekdays
     */
    public function build(
        string $preset,
        string $minute,
        string $hour,
        mixed $weekdays,
        string $monthDay,
    ): ?string {
        $minute = $this->sanitizePart($minute, '0');
        $hour = $this->sanitizePart($hour, '9');

        return match ($preset) {
            'hourly' => sprintf('%s * * * *', $minute),
            'daily' => sprintf('%s %s * * *', $minute, $hour),
            'weekly' => sprintf(
                '%s %s * * %s',
                $minute,
                $hour,
                $this->normalizeWeekdays($weekdays)
            ),
            'monthly' => sprintf(
                '%s %s %s * *',
                $minute,
                $hour,
                $monthDay === 'last' ? 'L' : $this->sanitizePart($monthDay, '1')
            ),
            default => null,
        };
    }

    private function sanitizePart(string $value, string $fallback): string
    {
        $value = trim($value);

        if ($value === '' || !ctype_digit($value)) {
            return $fallback;
        }

        return $value;
    }

    /**
     * @param mixed $weekdays
     */
    private function normalizeWeekdays(mixed $weekdays): string
    {
        if (is_string($weekdays) && $weekdays !== '') {
            $parts = preg_split('/\s*,\s*/', $weekdays) ?: [];
        } elseif (is_array($weekdays)) {
            $parts = $weekdays;
        } else {
            $parts = ['1', '2', '3', '4', '5'];
        }

        $clean = [];

        foreach ($parts as $part) {
            $part = trim((string) $part);

            if ($part === '' || !ctype_digit($part)) {
                continue;
            }

            $int = (int) $part;

            if ($int < 0 || $int > 6) {
                continue;
            }

            $clean[] = (string) $int;
        }

        $clean = array_values(array_unique($clean));

        if ($clean === []) {
            return '1,2,3,4,5';
        }

        return implode(',', $clean);
    }
}
