<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Hooks\BugReport;

use Espo\Core\Hook\Hook\AfterSave;
use Espo\Core\ORM\EntityManager;
use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * When a bug is closed, permanently remove screenshot attachments (DB + disk).
 *
 * Only attachments already owned by this BugReport (`parentType`/`parentId`/`field`)
 * are deleted — never arbitrary IDs from the request payload.
 *
 * @implements AfterSave<Entity>
 */
class AfterSaveCleanupScreenshots implements AfterSave
{
    public static int $order = 90;

    private const CLOSED_STATUS = 'Closed';
    private const FIELD = 'screenshots';

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function afterSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL) || $options->get(SaveOption::SILENT)) {
            return;
        }

        if ((string) $entity->get('status') !== self::CLOSED_STATUS) {
            return;
        }

        if (!$entity->isNew() && !$entity->isAttributeChanged('status')) {
            return;
        }

        $this->purgeScreenshots($entity);
    }

    private function purgeScreenshots(Entity $entity): void
    {
        $entityId = $entity->getId();

        if (!is_string($entityId) || $entityId === '') {
            return;
        }

        // Always scope by parent ownership. Blind delete-by-id lets a caller
        // reparent any readable Attachment onto this BugReport (Espo LinkCheck
        // only requires READ) and then destroy the original file on close.
        $attachments = $this->entityManager
            ->getRDBRepositoryByClass(Attachment::class)
            ->where([
                'parentType' => $entity->getEntityType(),
                'parentId' => $entityId,
                'field' => self::FIELD,
            ])
            ->find();

        foreach ($attachments as $attachment) {
            $this->entityManager->removeEntity($attachment);
        }

        $entity->set(self::FIELD . 'Ids', []);
        $entity->set('screenshotsClearedAt', date('Y-m-d H:i:s'));

        $this->entityManager->saveEntity($entity, [
            SaveOption::SILENT => true,
            SaveOption::SKIP_ALL => true,
        ]);
    }
}
