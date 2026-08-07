<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Hooks\BugReport;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Modules\BugTracker\Tools\BugReportMailer;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Email technician on create; email reporter on close.
 *
 * @implements AfterSave<Entity>
 */
class AfterSaveNotify implements AfterSave
{
    public static int $order = 80;

    private const CLOSED_STATUS = 'Closed';

    public function __construct(
        private BugReportMailer $bugReportMailer,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        if ($entity->isNew()) {
            $this->bugReportMailer->notifyTechnicianNewReport($entity);

            return;
        }

        if (
            (string) $entity->get('status') === self::CLOSED_STATUS
            && $entity->isAttributeChanged('status')
        ) {
            $this->bugReportMailer->notifyReporterClosed($entity);
        }
    }
}
