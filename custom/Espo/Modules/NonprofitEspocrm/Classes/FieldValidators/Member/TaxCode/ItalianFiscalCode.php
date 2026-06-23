<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Member\TaxCode;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\ORM\Entity;

/**
 * Validates Italian tax identifier: either a 16-char Codice Fiscale (alphanumeric)
 * or an 11-digit Partita IVA (numeric only).
 *
 * @implements Validator<Entity>
 */
class ItalianFiscalCode implements Validator
{
    /** 16-char Codice Fiscale (persons). */
    private const CF_PATTERN =
        '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';

    /** 11-digit Partita IVA (companies / associations). */
    private const PIVA_PATTERN = '/^\d{11}$/';

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $value = $entity->get($field);

        if ($value === null || $value === '') {
            return null;
        }

        if (!is_string($value)) {
            return Failure::create();
        }

        $value = strtoupper(trim($value));

        if (preg_match(self::PIVA_PATTERN, $value) === 1) {
            return null;
        }

        if (preg_match(self::CF_PATTERN, $value) === 1) {
            return null;
        }

        return Failure::create();
    }
}
