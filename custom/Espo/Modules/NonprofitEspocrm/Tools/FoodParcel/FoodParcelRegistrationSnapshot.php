<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\FoodParcel;

use Espo\ORM\Entity;

/**
 * Keeps PDF/list snapshot fields on FoodParcelRegistration in sync with source data.
 */
class FoodParcelRegistrationSnapshot
{
    /** @var string[] */
    private const ATTRIBUTES = [
        'taxCode',
        'phone',
        'phonePdf',
        'addressLine',
        'addressStreet',
        'addressCity',
        'addressState',
        'addressCountry',
        'addressPostalCode',
        'notesPdf',
        'entryDatesText',
        'exitDatesText',
    ];

    public function __construct(
        private FoodParcelContactSync $foodParcelContactSync,
    ) {}

    public function apply(Entity $registration): void
    {
        $this->foodParcelContactSync->syncFromContactId($registration);

        $registration->set([
            'notesPdf' => FoodParcelTextFormat::formatNotesPdf($registration->get('notes')),
            'entryDatesText' => FoodParcelTextFormat::formatDatesList($registration->get('entryDates')),
            'exitDatesText' => FoodParcelTextFormat::formatDatesList($registration->get('exitDates')),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function collect(Entity $registration): array
    {
        $state = [];

        foreach (self::ATTRIBUTES as $attribute) {
            $state[$attribute] = $registration->get($attribute);
        }

        return $state;
    }

    /**
     * @param array<string, mixed> $before
     */
    public function hasChanges(Entity $registration, array $before): bool
    {
        foreach (self::ATTRIBUTES as $attribute) {
            if ($registration->get($attribute) != ($before[$attribute] ?? null)) {
                return true;
            }
        }

        return false;
    }
}
