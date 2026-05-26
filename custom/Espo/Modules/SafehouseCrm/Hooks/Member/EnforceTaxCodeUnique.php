<?php

namespace Espo\Modules\SafehouseCrm\Hooks\Member;

use Espo\Core\Exceptions\Conflict;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Enforces taxCode (Codice Fiscale) uniqueness for Member records server-side.
 * Runs before PersonContactSync (order 10 < 15) so the error is raised early.
 */
class EnforceTaxCodeUnique implements BeforeSave
{
    public static int $order = 10;

    public function __construct(private EntityManager $entityManager) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        $taxCode = $entity->get('taxCode');

        if ($taxCode === null || $taxCode === '') {
            return;
        }

        $taxCode = strtoupper(trim($taxCode));

        $builder = $this->entityManager
            ->getRDBRepository('Member')
            ->where(['taxCode' => $taxCode]);

        if ($entity->hasId()) {
            $builder = $builder->where(['id!=' => $entity->getId()]);
        }

        $duplicate = $builder->findOne();

        if ($duplicate === null) {
            return;
        }

        throw new Conflict(sprintf(
            'A Member with Codice Fiscale "%s" already exists: %s (ID: %s). '
            . 'Please open the existing record or use a different fiscal code.',
            $taxCode,
            $duplicate->get('name') ?? '(no name)',
            $duplicate->getId()
        ));
    }
}
