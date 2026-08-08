<?php

namespace Espo\Modules\NonprofitEspocrm\Jobs;

use DateInterval;
use DateTime;
use Espo\Core\InjectableFactory;
use Espo\Core\Job\JobDataLess;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Core\Utils\Log;
use Espo\Modules\Crm\Entities\Reminder;
use Espo\Modules\NonprofitEspocrm\Tools\Reminder\Sender\PushReminder;
use Throwable;

/**
 * Cron twin of SendEmailReminders / SubmitPopupReminders for type=Push.
 */
class SubmitPushReminders implements JobDataLess
{
    private const TYPE_PUSH = 'Push';
    private const MAX_PORTION_SIZE = 20;

    public function __construct(
        private InjectableFactory $injectableFactory,
        private EntityManager $entityManager,
        private Config $config,
        private Log $log,
    ) {}

    public function run(): void
    {
        $dt = new DateTime();
        $now = $dt->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);
        $nowShifted = $dt
            ->sub(new DateInterval('PT1H'))
            ->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        $maxPortionSize = (int) ($this->config->get('pushReminderPortionSize') ?? self::MAX_PORTION_SIZE);

        $collection = $this->entityManager
            ->getRDBRepositoryByClass(Reminder::class)
            ->where([
                'type' => self::TYPE_PUSH,
                'remindAt<=' => $now,
                'startAt>' => $nowShifted,
            ])
            ->limit(0, max(1, $maxPortionSize))
            ->find();

        if (count($collection) === 0) {
            return;
        }

        $sender = $this->injectableFactory->create(PushReminder::class);

        foreach ($collection as $entity) {
            try {
                $sender->send($entity);
            } catch (Throwable $e) {
                $this->log->error("Push reminder '{$entity->getId()}': " . $e->getMessage());
            }

            $this->entityManager
                ->getRDBRepository(Reminder::ENTITY_TYPE)
                ->deleteFromDb($entity->getId());
        }
    }
}
