<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\DuplicateWhereBuilders;

use Espo\Core\Duplicate\WhereBuilder;
use Espo\Modules\NonprofitEspocrm\Tools\ContactEmailUniqueness;
use Espo\ORM\Entity;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Where\OrGroup;
use Espo\ORM\Query\Part\WhereItem;

/**
 * Duplicate-check helper for Contact email (warning). Hard block is UniqueAmongContacts.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
 *
 * @implements WhereBuilder<Entity>
 */
class Contact implements WhereBuilder
{
    public function __construct(
        private ContactEmailUniqueness $contactEmailUniqueness
    ) {}

    public function build(Entity $entity): ?WhereItem
    {
        $addresses = $this->contactEmailUniqueness->collectAddresses($entity);

        if ($addresses === []) {
            return null;
        }

        $orBuilder = OrGroup::createBuilder();

        foreach ($addresses as $address) {
            $orBuilder->add(
                Cond::equal(
                    Cond::column('emailAddress'),
                    $address
                )
            );
        }

        return $orBuilder->build();
    }
}
