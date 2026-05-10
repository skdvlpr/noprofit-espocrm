<?php

namespace Espo\Modules\SafehouseCrm\Jobs;

use Espo\Core\Job\JobDataLess;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\DateTime;

/**
 * Deactivates volunteer/employee records whose end date has passed.
 */
class DeactivateExpiredVolontarioDipendente implements JobDataLess
{
    private const ENTITY_TYPE = 'VolontarioDipendente';
    private const STATUS_INACTIVE = 'Inattivo';
    private const BATCH_SIZE = 100;

    /**
     * @param EntityManager $entityManager ORM entity manager.
     */
    public function __construct(private EntityManager $entityManager)
    {
    }

    /**
     * Marks up to 100 expired active records as inactive.
     *
     * @return void
     */
    public function run(): void
    {
        $today = date(DateTime::SYSTEM_DATE_FORMAT);

        $recordList = $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->where([
                'dataFine<=' => $today,
                'dataFine!=' => null,
                'status!=' => self::STATUS_INACTIVE,
            ])
            ->order('dataFine')
            ->limit(0, self::BATCH_SIZE)
            ->find();

        foreach ($recordList as $record) {
            $record->set('status', self::STATUS_INACTIVE);

            $this->entityManager->saveEntity($record);
        }
    }
}
