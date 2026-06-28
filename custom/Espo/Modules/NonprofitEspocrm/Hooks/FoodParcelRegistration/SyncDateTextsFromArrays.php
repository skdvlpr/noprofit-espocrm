<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\FoodParcelRegistration;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

class SyncDateTextsFromArrays implements BeforeSave
{
    public static int $order = 9;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $entity->set([
            'entryDatesText' => $this->formatDates($entity->get('entryDates')),
            'exitDatesText' => $this->formatDates($entity->get('exitDates')),
        ]);
    }

    /**
     * @param mixed $dates
     */
    private function formatDates(mixed $dates): string
    {
        if (!is_array($dates)) {
            return '';
        }

        $lines = [];

        foreach ($dates as $date) {
            if ($date !== null && $date !== '') {
                $lines[] = $this->formatDateLine((string) $date);
            }
        }

        $lines = array_values(array_unique($lines));
        sort($lines);

        return $lines === [] ? '' : implode("\n", $lines);
    }

    private function formatDateLine(string $value): string
    {
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) !== 1) {
            return $value;
        }

        [$year, $month, $day] = explode('-', $value);

        return $day . '.' . $month . '.' . $year;
    }
}
