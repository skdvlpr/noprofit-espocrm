<?php

declare(strict_types=1);

namespace Espo\Modules\BugTracker\Hooks\BugReport;

use Espo\Core\Hook\Hook\AfterRemove;
use Espo\Core\ORM\EntityManager;
use Espo\Entities\Attachment;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\RemoveOptions;

/**
 * Delete screenshot files when a bug report itself is removed.
 *
 * @implements AfterRemove<Entity>
 */
class AfterRemoveCleanupScreenshots implements AfterRemove
{
    public static int $order = 90;

    private const FIELD = 'screenshots';

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    public function afterRemove(Entity $entity, RemoveOptions $options): void
    {
        $attachments = $this->entityManager
            ->getRDBRepositoryByClass(Attachment::class)
            ->where([
                'parentType' => $entity->getEntityType(),
                'parentId' => $entity->getId(),
                'field' => self::FIELD,
            ])
            ->find();

        foreach ($attachments as $attachment) {
            $this->entityManager->removeEntity($attachment);
        }
    }
}
