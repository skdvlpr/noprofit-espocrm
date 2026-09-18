<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\User\EmailAddress;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\NonprofitEspocrm\Tools\UserEmailUniqueness;
use Espo\ORM\Entity;

/**
 * Hard unique email among Users (not skippable duplicate dialog).
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements Validator<Entity>
 */
class UniqueAmongUsers implements Validator
{
    public function __construct(
        private UserEmailUniqueness $userEmailUniqueness
    ) {}

    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        if ($this->userEmailUniqueness->hasConflict($entity)) {
            return Failure::create();
        }

        return null;
    }
}
