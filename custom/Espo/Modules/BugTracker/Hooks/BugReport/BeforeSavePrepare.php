<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Hooks\BugReport;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Auto-name + default assignee; block creates when Bug Tracker is disabled.
 *
 * @implements BeforeSave<Entity>
 */
class BeforeSavePrepare implements BeforeSave
{
    public static int $order = 5;

    private const ENTITY_LABEL = 'BugReport';

    public function __construct(
        private Config $config,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($entity->isNew() && $this->config->get('bugTrackerEnabled') === false) {
            throw new Forbidden('Bug Tracker is disabled.');
        }

        if ($entity->isNew()) {
            $entity->set('name', $this->buildStandardName());
            $this->applyDefaultAssignee($entity);
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
