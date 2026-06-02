<?php

namespace Espo\Modules\GoogleIntegration\Hooks\CalendarDateSource;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave as BeforeSaveHook;
use Espo\Core\Utils\Metadata;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * @implements BeforeSaveHook<Entity>
 */
class BeforeSave implements BeforeSaveHook
{
    public static int $order = 9;

    /** Espo datetimeOptional companions — use main field + allDay checkbox instead. */
    private const DATETIME_COMPANION_FIELDS = [
        'dateStartDate' => 'dateStart',
        'dateEndDate' => 'dateEnd',
    ];

    public function __construct(
        private Metadata $metadata
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $targetEntityType = $entity->get('targetEntityType');
        $dateField = $entity->get('dateField');

        if (!is_string($targetEntityType) || $targetEntityType === '' || !is_string($dateField) || $dateField === '') {
            return;
        }

        $this->assertValidDateField($targetEntityType, $dateField);

        $endDateField = $entity->get('endDateField');

        if (is_string($endDateField) && $endDateField !== '') {
            $this->assertValidDateField($targetEntityType, $endDateField);
        }

        $fieldType = $this->metadata->get(['entityDefs', $targetEntityType, 'fields', $dateField, 'type']);

        if ($fieldType === 'date') {
            $entity->set('allDay', true);
        }
    }

    private function assertValidDateField(string $targetEntityType, string $dateField): void
    {
        if (isset(self::DATETIME_COMPANION_FIELDS[$dateField])) {
            $mainField = self::DATETIME_COMPANION_FIELDS[$dateField];

            throw new BadRequest(
                "Calendar date source cannot use companion field `{$dateField}`. "
                . "Select `{$mainField}` and use the All day checkbox instead."
            );
        }

        $fieldDef = $this->metadata->get(['entityDefs', $targetEntityType, 'fields', $dateField]) ?? [];

        if (!empty($fieldDef['utility'])) {
            throw new BadRequest(
                "Calendar date source cannot use utility field `{$dateField}`."
            );
        }

        $fieldType = $fieldDef['type'] ?? null;

        if (!in_array($fieldType, ['date', 'datetime', 'datetimeOptional'], true)) {
            throw new BadRequest(
                "Field `{$dateField}` on {$targetEntityType} is not a date/datetime field."
            );
        }
    }
}
