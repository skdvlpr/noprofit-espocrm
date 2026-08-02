<?php

namespace Espo\Modules\WorkflowEngine\Jobs;

use Cron\CronExpression;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Job\JobDataLess;
use Espo\Core\ORM\EntityManager;
use Espo\Modules\WorkflowEngine\Services\WorkflowRunner;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Every minute: active triggerType=scheduled definitions whose cron is due
 * are evaluated against a bounded batch of target-entity records.
 */
class RunScheduledWorkflows implements JobDataLess
{
    private const DEFINITION_LIMIT = 50;
    private const RECORD_BATCH = 100;

    public function __construct(
        private EntityManager $entityManager,
        private WorkflowRunner $workflowRunner,
        private LoggerInterface $log,
    ) {}

    public function run(): void
    {
        $now = new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $scanned = 0;

        $definitions = $this->entityManager
            ->getRDBRepository('WorkflowDefinition')
            ->where([
                'isActive' => true,
                'triggerType' => 'scheduled',
            ])
            ->order('executionOrder')
            ->limit(0, self::DEFINITION_LIMIT)
            ->find();

        foreach ($definitions as $definition) {
            $scanned++;
            $scheduling = trim((string) ($definition->get('scheduling') ?? ''));
            $entityType = trim((string) ($definition->get('targetEntityType') ?? ''));

            if ($scheduling === '' || $entityType === '') {
                continue;
            }

            try {
                if (!CronExpression::isValidExpression($scheduling)) {
                    $this->log->warning(
                        'WorkflowEngine scheduled: invalid cron on {id}',
                        ['id' => $definition->getId()]
                    );

                    continue;
                }

                $cron = new CronExpression($scheduling);

                // Due if this minute matches (job runs every minute).
                if (!$cron->isDue($now)) {
                    continue;
                }

                if (!$this->entityManager->hasRepository($entityType)) {
                    continue;
                }

                $records = $this->entityManager
                    ->getRDBRepository($entityType)
                    ->limit(0, self::RECORD_BATCH)
                    ->find();

                foreach ($records as $record) {
                    $this->workflowRunner->runDefinitionOnEntity($definition, $record);
                }
            } catch (Throwable $e) {
                $this->log->error(
                    'WorkflowEngine scheduled failed: ' . $e->getMessage(),
                    [
                        'workflowDefinitionId' => $definition->getId(),
                        'exception' => $e,
                    ]
                );
            }
        }
    }
}
