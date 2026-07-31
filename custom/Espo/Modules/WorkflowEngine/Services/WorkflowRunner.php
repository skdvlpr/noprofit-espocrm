<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Services;

use Espo\ORM\Entity;

/**
 * W1 observation boundary.
 *
 * Evaluation, persistence, and side effects begin in later workflow slices.
 */
class WorkflowRunner
{
    public function observe(Entity $entity): void
    {
    }
}
