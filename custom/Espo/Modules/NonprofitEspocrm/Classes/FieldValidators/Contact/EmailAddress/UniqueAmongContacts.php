<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Contact\EmailAddress;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\NonprofitEspocrm\Tools\ContactEmailUniqueness;
use Espo\ORM\Entity;

/**
 * Hard unique email among Contacts (not skippable duplicate dialog).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
 *
 * @implements Validator<Entity>
 */
class UniqueAmongContacts implements Validator
{
    public function __construct(
        private ContactEmailUniqueness $contactEmailUniqueness
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        if ($this->contactEmailUniqueness->hasConflict($entity)) {
            return Failure::create();
        }

        return null;
    }
}
