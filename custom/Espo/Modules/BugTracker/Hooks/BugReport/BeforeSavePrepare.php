<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Hooks\BugReport;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Auto-name + default assignee; block creates when disabled;
 * after create only status + description may change (server-side).
 *
 * @implements BeforeSave<Entity>
 */
class BeforeSavePrepare implements BeforeSave
{
    public static int $order = 5;

    private const ENTITY_LABEL = 'BugReport';

    /** @var list<string> */
    private const LOCKED_AFTER_CREATE = [
        'name',
        'pageUrl',
        'pageTitle',
        'screenshotsIds',
        'assignedUserId',
        'teamsIds',
    ];

    public function __construct(
        private Config $config,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        if ($entity->isNew() && $this->config->get('bugTrackerEnabled') === false) {
            throw new Forbidden('Bug Tracker is disabled.');
        }

        if ($entity->isNew()) {
            $entity->set('name', $this->buildStandardName());
            $this->applyDefaultAssignee($entity);

            return;
        }

        $this->enforceLockedFields($entity);
    }

    private function enforceLockedFields(Entity $entity): void
    {
        foreach (self::LOCKED_AFTER_CREATE as $attribute) {
            if (!$entity->isAttributeChanged($attribute)) {
                continue;
            }

            // Screenshot purge on close uses SaveOption::SKIP_ALL / SILENT and bypasses this guard.
            throw new Forbidden(
                'Only status and description can be edited on an existing bug report.'
            );
        }
    }

    private function buildStandardName(): string
    {
        $stamp = date('dmY-Hi');
        $uuid = bin2hex(random_bytes(4));

        return "{$stamp}-" . self::ENTITY_LABEL . "-{$uuid}";
    }

    private function applyDefaultAssignee(Entity $entity): void
    {
        if ($entity->get('assignedUserId')) {
            return;
        }

        $defaultId = $this->config->get('bugTrackerDefaultAssignedUserId');

        if (!is_string($defaultId) || $defaultId === '') {
            $defaultId = $this->config->get('bugTrackerTechnicianUserId');
        }

        if (is_string($defaultId) && $defaultId !== '') {
            $entity->set('assignedUserId', $defaultId);
        }
    }
}
