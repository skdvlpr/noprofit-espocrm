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
        $amountGrossRaw = $entity->get('amountGross');
        $hasGross = $amountGrossRaw !== null && $amountGrossRaw !== '';

        if ($entity->isNew() && !$hasGross) {
            throw new BadRequest(
                'Gross amount (lordo) is required. Net amount is calculated automatically as lordo − commission.'
            );
        }

        if ($hasGross) {
            $amountGross = (float) $amountGrossRaw;
            $commissionAmount = (float) ($entity->get('commissionAmount') ?? 0);
            $commissionPercent = (float) ($entity->get('commissionPercent') ?? 0);

            if ($amountGross < 0) {
                throw new BadRequest('Gross amount (lordo) cannot be negative.');
            }

            if ($commissionAmount < 0) {
                throw new BadRequest('Commission cannot be negative.');
            }

            if ($commissionAmount - $amountGross > 0.0001) {
                throw new BadRequest('Commission cannot exceed gross amount (lordo).');
            }

            if ($commissionPercent < 0 || $commissionPercent > 100) {
                throw new BadRequest('Commission % must be between 0 and 100.');
            }

            // Net may be 0 when fee equals gross or when lordo was cleared to 0.
            if ($amount < 0) {
                throw new BadRequest('Net amount cannot be negative.');
            }
        } elseif ($amount <= 0) {
            // Legacy rows without amountGross (pre-migration) still require a positive net.
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
