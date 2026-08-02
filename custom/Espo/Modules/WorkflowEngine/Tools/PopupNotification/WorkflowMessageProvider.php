<?php

declare(strict_types=1);

namespace Espo\Modules\WorkflowEngine\Tools\PopupNotification;

use DateInterval;
use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Utils\DateTime as DateTimeUtil;
use Espo\Entities\Notification;
use Espo\Entities\User;
use Espo\ORM\EntityManager;
use Espo\Tools\PopupNotification\Item;
use Espo\Tools\PopupNotification\Provider;
use Exception;
use stdClass;

/**
 * Exposes unread WorkflowEngine Message notifications as native Espo popups.
 */
class WorkflowMessageProvider implements Provider
{
    private const LOOKBACK_HOURS = 24;

    private const MAX_ITEMS = 10;

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    /**
     * @return Item[]
     * @throws Exception
     */
    public function get(User $user): array
    {
        $since = (new DateTimeImmutable('now', new DateTimeZone('UTC')))
            ->sub(new DateInterval('PT' . self::LOOKBACK_HOURS . 'H'))
            ->format(DateTimeUtil::SYSTEM_DATE_TIME_FORMAT);

        /** @var iterable<Notification> $rows */
        $rows = $this->entityManager
            ->getRDBRepositoryByClass(Notification::class)
            ->where([
                'userId' => $user->getId(),
                'type' => Notification::TYPE_MESSAGE,
                'read' => false,
                'createdAt>=' => $since,
            ])
            ->order('createdAt', 'DESC')
            ->limit(0, 40)
            ->find();

        $items = [];

        foreach ($rows as $notification) {
            $data = $notification->getData();

            if (!$data instanceof stdClass || empty($data->workflowEngine)) {
                continue;
            }

            $related = $notification->getRelated();
            $message = (string) ($notification->get('message') ?? '');

            $items[] = new Item(
                $notification->getId(),
                (object) [
                    'message' => $message,
                    'relatedType' => $related?->getEntityType(),
                    'relatedId' => $related?->getId(),
                    'relatedName' => is_string($data->relatedName ?? null)
                        ? $data->relatedName
                        : null,
                ]
            );

            if (count($items) >= self::MAX_ITEMS) {
                break;
            }
        }

        return $items;
    }
}
