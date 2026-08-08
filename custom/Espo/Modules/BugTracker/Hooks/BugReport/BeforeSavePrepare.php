<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Hooks\BugReport;

use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Auto-name + default assignee; block creates when Bug Tracker is disabled.
 * Reject foreign Attachment IDs on screenshots (prevent steal+destroy on close).
 *
 * @implements BeforeSave<Entity>
 */
class BeforeSavePrepare implements BeforeSave
{
    public static int $order = 5;

    private const ENTITY_LABEL = 'BugReport';
    private const SCREENSHOTS_FIELD = 'screenshots';

    public function __construct(
        private Config $config,
        private EntityManager $entityManager,
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

        $this->filterScreenshotIds($entity);
    }

    /**
     * Keep only Attachments that are pending uploads for this BugReport.screenshots
     * field, or already parented to this record. Runs before FieldProcessing so
     * foreign IDs are never reparented onto the bug (and later wiped on Closed).
     */
    private function filterScreenshotIds(Entity $entity): void
    {
        $idsAttribute = self::SCREENSHOTS_FIELD . 'Ids';

        // Only when the client/API is writing screenshotsIds (not on unrelated updates).
        if (!$entity->has($idsAttribute) || !$entity->isAttributeChanged($idsAttribute)) {
            return;
        }

        /** @var mixed $rawIds */
        $rawIds = $entity->get($idsAttribute);

        if (!is_array($rawIds)) {
            return;
        }

        $allowed = [];

        foreach ($rawIds as $id) {
            if (!is_string($id) || $id === '') {
                continue;
            }

            $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $id);

            if (
                $attachment instanceof Attachment &&
                $this->isAllowedScreenshotAttachment($entity, $attachment)
            ) {
                $allowed[] = $id;
            }
        }

        $entity->set($idsAttribute, array_values(array_unique($allowed)));
    }

    private function isAllowedScreenshotAttachment(Entity $bugReport, Attachment $attachment): bool
    {
        if ((string) $attachment->get('field') !== self::SCREENSHOTS_FIELD) {
            return false;
        }

        $bugId = $bugReport->getId();
        $parentType = $attachment->get('parentType');
        $parentId = $attachment->get('parentId');
        $relatedType = $attachment->get('relatedType');
        $relatedId = $attachment->get('relatedId');

        // Already owned by this BugReport.
        if (
            $parentType === 'BugReport' &&
            is_string($bugId) &&
            $bugId !== '' &&
            $parentId === $bugId
        ) {
            return true;
        }

        // Owned by a different parent — never steal.
        if (is_string($parentId) && $parentId !== '') {
            return false;
        }

        // Pending upload created for BugReport.screenshots (relatedType set at upload).
        if ($relatedType !== 'BugReport') {
            return false;
        }

        if (is_string($relatedId) && $relatedId !== '') {
            return is_string($bugId) && $bugId !== '' && $relatedId === $bugId;
        }

        return true;
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
