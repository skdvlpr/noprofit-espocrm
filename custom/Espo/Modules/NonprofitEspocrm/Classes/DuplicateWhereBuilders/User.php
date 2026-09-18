<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\DuplicateWhereBuilders;

use Espo\Core\Duplicate\WhereBuilder;
use Espo\Modules\NonprofitEspocrm\Tools\UserEmailUniqueness;
use Espo\ORM\Entity;
use Espo\ORM\Query\Part\Condition as Cond;
use Espo\ORM\Query\Part\Where\OrGroup;
use Espo\ORM\Query\Part\WhereItem;

/**
 * Duplicate-check helper for User email (warning). Hard block is UniqueAmongUsers.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/duplicate-check.md
 *
 * @implements WhereBuilder<Entity>
 */
class User implements WhereBuilder
{
    public function __construct(
        private UserEmailUniqueness $userEmailUniqueness
    ) {}

    public function build(Entity $entity): ?WhereItem
    {
        $addresses = $this->userEmailUniqueness->collectAddresses($entity);

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
