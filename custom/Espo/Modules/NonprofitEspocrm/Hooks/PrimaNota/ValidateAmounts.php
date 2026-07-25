<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

class ValidateAmounts implements BeforeSave
{
    public static int $order = 15;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        $entryType = $entity->get('entryType');
        $amount = (float) ($entity->get('amount') ?? 0);

        if (!$entryType) {
            $amountIn = (float) ($entity->get('amountIn') ?? 0);
            $amountOut = (float) ($entity->get('amountOut') ?? 0);

            if ($amountIn > 0 && $amountOut <= 0) {
                $entryType = 'Income';
                $amount = $amountIn;
            } elseif ($amountOut > 0 && $amountIn <= 0) {
                $entryType = 'Expense';
                $amount = $amountOut;
            }

            if ($entryType) {
                $entity->set('entryType', $entryType);
                $entity->set('amount', $amount);
            }
        }

        $entryType = $entity->get('entryType');

        if ($entryType !== 'Income' && $entryType !== 'Expense') {
            throw new BadRequest('Prima Nota entry type must be Income or Expense.');
        }

        $amount = (float) ($entity->get('amount') ?? 0);
        $amountGross = $entity->get('amountGross');
        $hasGross = $amountGross !== null && $amountGross !== '';

        // Net may be 0 when formula clamps after commission >= gross (Stripe edge case).
        // Legacy rows without amountGross still require a strictly positive amount.
        if ($amount < 0 || (!$hasGross && $amount <= 0)) {
            throw new BadRequest('Prima Nota entry requires a positive amount.');
        }

        if ($entryType === 'Income') {
            $entity->set('amountIn', $amount);
            $entity->set('amountOut', 0);
        } else {
            $entity->set('amountIn', 0);
            $entity->set('amountOut', $amount);
        }
    }
}
