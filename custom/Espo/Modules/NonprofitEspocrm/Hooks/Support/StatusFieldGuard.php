<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Support;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\NonprofitEspocrm\Tools\StatusGuard;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Shared logic: block manual status changes unless safehouseSkipStatusGuard is set.
 */
trait StatusFieldGuard
{
    /**
     * @throws Forbidden
     */
    protected function guardStatusField(
        Entity $entity,
        SaveOptions $options,
        string $defaultOnCreate,
        bool $forceDefaultOnCreate = true
    ): void {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SKIP_HOOKS)) {
            return;
        }

        if ($options->get(StatusGuard::SKIP_OPTION)) {
            return;
        }

        if ($entity->isNew()) {
            if ($forceDefaultOnCreate) {
                $entity->set('status', $defaultOnCreate);
            }

            return;
        }

        if (!$entity->isAttributeChanged('status')) {
            return;
        }

        $fetched = $entity->getFetched('status');
        $entity->set('status', $fetched);

        throw new Forbidden("Status is system-managed and cannot be changed manually.");
    }

    /**
     * For Member / VolunteerEmployee: strip client status changes early;
     * formula (later hook) recalculates from date window.
     */
    protected function stripManualStatusChange(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SKIP_HOOKS)) {
            return;
        }

        if ($options->get(StatusGuard::SKIP_OPTION)) {
            return;
        }

        if ($entity->isNew() || !$entity->isAttributeChanged('status')) {
            return;
        }

        $entity->set('status', $entity->getFetched('status'));
    }
}
