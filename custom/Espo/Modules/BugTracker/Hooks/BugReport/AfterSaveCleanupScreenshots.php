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
        $idList = $entity->getLinkMultipleIdList(self::FIELD);

        if ($idList === []) {
            // Also query by parent in case Ids were already empty in memory.
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
        } else {
            foreach ($idList as $id) {
                $attachment = $this->entityManager->getEntityById(Attachment::ENTITY_TYPE, $id);

                if ($attachment) {
                    $this->entityManager->removeEntity($attachment);
                }
            }
        }

        $entity->set(self::FIELD . 'Ids', []);
        $entity->set('screenshotsClearedAt', date('Y-m-d H:i:s'));

        $this->entityManager->saveEntity($entity, [
            SaveOption::SILENT => true,
            SaveOption::SKIP_ALL => true,
        ]);
    }
}
