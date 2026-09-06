<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\Rebuild\RebuildAction;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\UpdateBuilder;

/**
 * Existing Cash / DonorPocket rows never run Formula. After the digital-total
 * filter switches to excludeFromDigitalReports, those rows must already be true
 * or Contanti would start counting (DB default for the new bool is false).
 *
 * @noinspection PhpUnused
 */
class BackfillPrimaNotaDigitalExclude implements RebuildAction
{
    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function process(): void
    {
        $query = UpdateBuilder::create()
            ->in('PrimaNota')
            ->set(['excludeFromDigitalReports' => true])
            ->where([
                'donationPaymentProvider' => ['Cash', 'DonorPocket'],
                'excludeFromDigitalReports!=' => true,
            ])
            ->build();

        $this->entityManager->getQueryExecutor()->execute($query);
    }
}
