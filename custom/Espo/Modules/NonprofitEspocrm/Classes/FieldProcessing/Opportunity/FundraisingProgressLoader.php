<?php

namespace Espo\Modules\NonprofitEspocrm\Classes\FieldProcessing\Opportunity;

use Espo\Core\FieldProcessing\Loader;
use Espo\Core\FieldProcessing\Loader\Params;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\Crm\Entities\Opportunity;
use Espo\ORM\Entity;

/**
 * @implements Loader<Opportunity>
 */
class FundraisingProgressLoader implements Loader
{
    /** @var list<string> */
    private const PROGRESS_STAGES = [
        'Fundraising',
        'Closed Won',
        'Closed Lost',
    ];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function process(Entity $entity, Params $params): void
    {
        $stage = $entity->get('stage');

        if (!in_array($stage, self::PROGRESS_STAGES, true)) {
            return;
        }

        $target = (float) ($entity->get('amount') ?? 0);

        $income = (float) $this->entityManager
            ->getRDBRepository('PrimaNota')
            ->where([
                'financingId' => $entity->getId(),
                'entryType' => 'Income',
            ])
            ->sum('amount');

        $expense = (float) $this->entityManager
            ->getRDBRepository('PrimaNota')
            ->where([
                'financingId' => $entity->getId(),
                'entryType' => 'Expense',
            ])
            ->sum('amount');

        $collected = $income - $expense;

        $percent = 0;

        if ($target > 0) {
            $percent = (int) min(100, max(0, round($collected / $target * 100)));
        }

        $entity->set('fundraisingCollectedAmount', $collected);
        $entity->set('fundraisingTargetAmount', $target);
        $entity->set('fundraisingProgressPercent', $percent);
        $entity->set('fundraisingProgress', (string) $percent);
    }
}
