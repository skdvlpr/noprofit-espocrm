<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\FoodParcel;

use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

class DateLogsTextSync
{
    public function __construct(private EntityManager $entityManager) {}

    public function syncForRegistrationId(string $registrationId): void
    {
        $entity = $this->entityManager->getEntityById('FoodParcelRegistration', $registrationId);

        if (!$entity) {
            return;
        }

        $logs = $this->entityManager
            ->getRDBRepository('FoodParcelDateLog')
            ->where(['foodParcelRegistrationId' => $registrationId])
            ->order('entryDate', 'ASC')
            ->find();

        $lines = [];

        foreach ($logs as $log) {
            $entry = $log->get('entryDate') ?? '—';
            $exit = $log->get('exitDate') ?? '—';
            $lines[] = $entry . ' | ' . $exit;
        }

        $text = $lines === [] ? '' : implode("\n", $lines);

        if ($entity->get('dateLogsText') === $text) {
            return;
        }

        $entity->set('dateLogsText', $text);

        $this->entityManager
            ->getRDBRepository('FoodParcelRegistration')
            ->save($entity, [SaveOption::SKIP_ALL => true]);
    }
}
