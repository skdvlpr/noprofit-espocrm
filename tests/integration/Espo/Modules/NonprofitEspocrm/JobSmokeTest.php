<?php

declare(strict_types=1);

namespace tests\integration\Espo\Modules\NonprofitEspocrm;

use Espo\Core\InjectableFactory;
use Espo\Core\Job\Job\Data as JobData;
use Espo\Modules\NonprofitEspocrm\Jobs\AutoConfirmFullyStaffedPlan;
use Espo\Modules\NonprofitEspocrm\Jobs\CompletePastActivityOfferSlots;
use Espo\Modules\NonprofitEspocrm\Jobs\NotifyPlanUpdated;
use Espo\Modules\NonprofitEspocrm\Jobs\NotifySlotScheduleChange;
use Espo\Modules\NonprofitEspocrm\Jobs\ReconcileFullyStaffedPlans;
use Espo\Modules\NonprofitEspocrm\Jobs\SubmitPushReminders;
use tests\integration\Espo\Support\SafehouseBaseTestCase;

/**
 * Smoke: every NonprofitEspocrm Job class run() with empty work must not throw.
 */
class JobSmokeTest extends SafehouseBaseTestCase
{
    /**
     * @return iterable<string, array{class-string, bool}>
     */
    public static function jobProvider(): iterable
    {
        yield 'CompletePastActivityOfferSlots' => [CompletePastActivityOfferSlots::class, false];
        yield 'ReconcileFullyStaffedPlans' => [ReconcileFullyStaffedPlans::class, false];
        yield 'SubmitPushReminders' => [SubmitPushReminders::class, false];
        yield 'NotifySlotScheduleChange' => [NotifySlotScheduleChange::class, true];
        yield 'NotifyPlanUpdated' => [NotifyPlanUpdated::class, true];
        yield 'AutoConfirmFullyStaffedPlan' => [AutoConfirmFullyStaffedPlan::class, true];
    }

    /**
     * @dataProvider jobProvider
     * @param class-string $jobClass
     */
    public function testJobRunWithEmptyWorkDoesNotThrow(string $jobClass, bool $needsData): void
    {
        $factory = $this->getContainer()->getByClass(InjectableFactory::class);
        $job = $factory->create($jobClass);

        if ($needsData) {
            $job->run(JobData::create());
        } else {
            $job->run();
        }

        $this->addToAssertionCount(1);
    }
}
