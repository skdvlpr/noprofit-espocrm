<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Hooks\BugReport;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Config;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Auto-name + default assignee; block creates when disabled.
 * After create: reporters may edit description + screenshots only;
 * managers (edit level all / admin) may also change status + assignee.
 * name / pageUrl / pageTitle are never editable after auto-fill.
 *
 * @implements BeforeSave<Entity>
 */
class BeforeSavePrepare implements BeforeSave
{
    public static int $order = 5;

    private const ENTITY_LABEL = 'BugReport';

    /** @var list<string> */
    private const ALWAYS_LOCKED = [
        'name',
        'pageUrl',
        'pageTitle',
    ];

    /** @var list<string> */
    private const REPORTER_ALLOWED = [
        'description',
        'screenshotsIds',
        'screenshotsNames',
        'screenshotsTypes',
    ];

    /** @var list<string> */
    private const MANAGER_EXTRA_ALLOWED = [
        'status',
        'assignedUserId',
        'teamsIds',
    ];

    public function __construct(
        private Config $config,
        private User $user,
        private Acl $acl,
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

        $this->enforceEditableFields($entity);
    }

    private function enforceEditableFields(Entity $entity): void
    {
        foreach (self::ALWAYS_LOCKED as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new Forbidden(
                    'Page URL, page title and auto title cannot be edited.'
                );
            }
        }

        $managerExtras = self::MANAGER_EXTRA_ALLOWED;

        if (!$this->isManagerEditor()) {
            foreach ($managerExtras as $attribute) {
                if ($entity->isAttributeChanged($attribute)) {
                    throw new Forbidden(
                        'Only description and screenshots can be edited on a bug report.'
                    );
                }
            }
        }

        // Block other business fields reporters might try to PATCH.
        foreach (['createdById', 'createdAt'] as $attribute) {
            if ($entity->isAttributeChanged($attribute)) {
                throw new Forbidden('This field cannot be edited.');
            }
        }
    }

    private function isManagerEditor(): bool
    {
        if ($this->user->isAdmin()) {
            return true;
        }

        return $this->acl->getLevel('BugReport', Table::ACTION_EDIT) === Table::LEVEL_ALL;
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
