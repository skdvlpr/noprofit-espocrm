<?php

namespace Espo\Modules\SafehouseCrm\Classes\FieldValidators\Associati\Cf;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\ORM\Entity;

/**
 * Validates the Italian fiscal code format for Associati records.
 *
 * @implements Validator<Entity>
 */
class ItalianFiscalCode implements Validator
{
    private const PATTERN =
        '/^[A-Z]{6}[0-9LMNPQRSTUV]{2}[ABCDEHLMPRST][0-9LMNPQRSTUV]{2}[A-Z][0-9LMNPQRSTUV]{3}[A-Z]$/';

    /**
     * Validate an optional Italian fiscal code.
     *
     * @param Entity $entity Entity being validated.
     * @param string $field Field name.
     * @param Data $data Field validation data.
     * @return Failure|null
     */
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

        if (preg_match(self::PATTERN, $value) !== 1) {
            return Failure::create();
        }

        return null;
    }
}
