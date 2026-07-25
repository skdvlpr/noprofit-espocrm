<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

class ValidateAmounts implements BeforeSave
{
    use TranslatesPrimaNotaMessages;

    public static int $order = 15;

    public function __construct(Language $language)
    {
        $this->language = $language;
    }

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
            throw new BadRequest($this->msg('entryTypeRequired'));
        }

        $amount = (float) ($entity->get('amount') ?? 0);
        $amountGrossRaw = $entity->get('amountGross');
        $hasGross = $amountGrossRaw !== null && $amountGrossRaw !== '';

        if ($entity->isNew() && !$hasGross) {
            throw new BadRequest($this->msg('grossRequired'));
        }

        if ($hasGross) {
            $amountGross = (float) $amountGrossRaw;
            $commissionAmount = (float) ($entity->get('commissionAmount') ?? 0);
            $commissionPercent = (float) ($entity->get('commissionPercent') ?? 0);

            if ($amountGross < 0) {
                throw new BadRequest($this->msg('grossNegative'));
            }

            if ($commissionAmount < 0) {
                throw new BadRequest($this->msg('commissionNegative'));
            }

            if ($commissionAmount - $amountGross > 0.0001) {
                throw new BadRequest($this->msg('commissionExceedsGross'));
            }

            if ($commissionPercent < 0 || $commissionPercent > 100) {
                throw new BadRequest($this->msg('commissionPercentRange'));
            }

            if ($amount < 0) {
                throw new BadRequest($this->msg('netNegative'));
            }
        } elseif ($amount <= 0) {
            throw new BadRequest($this->msg('positiveAmountRequired'));
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
