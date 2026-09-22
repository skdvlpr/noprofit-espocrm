<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\FieldValidators\Contact\ContactType;

use Espo\Core\FieldValidation\Validator;
use Espo\Core\FieldValidation\Validator\Data;
use Espo\Core\FieldValidation\Validator\Failure;
use Espo\Modules\NonprofitEspocrm\Tools\ContactTypeSet;
use Espo\ORM\Entity;

/**
 * Legal Contact type sets: empty, one option, Volunteer+Member, Employee+Member.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 *
 * @implements Validator<Entity>
 */
class LegalCombination implements Validator
{
    public function validate(Entity $entity, string $field, Data $data): ?Failure
    {
        $types = ContactTypeSet::normalize($entity->get($field));

        if (!ContactTypeSet::isLegal($types)) {
            return Failure::create();
        }

        return null;
    }
}
