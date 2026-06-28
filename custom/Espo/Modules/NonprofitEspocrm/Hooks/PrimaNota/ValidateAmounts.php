<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\PrimaNota;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

class ValidateAmounts implements BeforeSave
{
    public static int $order = 5;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        $amountIn = (float) ($entity->get('amountIn') ?? 0);
        $amountOut = (float) ($entity->get('amountOut') ?? 0);

        if ($amountIn > 0 && $amountOut > 0) {
            throw new BadRequest('Prima Nota entry cannot have both income and expense amounts.');
        }

        if ($amountIn <= 0 && $amountOut <= 0) {
            throw new BadRequest('Prima Nota entry requires either income or expense amount.');
        }
    }
}
