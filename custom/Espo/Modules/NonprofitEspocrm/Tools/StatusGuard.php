<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

/**
 * SaveOption key that allows trusted code (ShiftPlanningService, jobs, formula path)
 * to change system-managed status fields. UI/REST without this flag cannot.
 */
final class StatusGuard
{
    public const SKIP_OPTION = 'safehouseSkipStatusGuard';

    private function __construct() {}
}
