<?php

namespace Espo\Modules\NonprofitEspocrm\Services;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\ORM\Query\Select;
use RuntimeException;

/**
 * Legacy calendar hook: ActivityOfferSlot has no assignedUser/users attendees.
 * Native Espo calendar base query would filter assignedUserId and hide all shifts.
 * This query uses ACL only so shifts appear on the CRM calendar without GoogleIntegration.
 */
class ActivityOfferSlot
{
    public function __construct(
        private SelectBuilderFactory $selectBuilderFactory,
    ) {}

    public function getCalenderQuery(
        string $userId,
        string $from,
        string $to,
        bool $skipAcl = false
    ): Select {
        // $userId kept for signature compatibility with Calendar\Service.
        unset($userId);

        $builder = $this->selectBuilderFactory
            ->create()
            ->from('ActivityOfferSlot');

        if (!$skipAcl) {
            $builder->withStrictAccessControl();
        }

        $select = [
            ['"ActivityOfferSlot"', 'scope'],
            'id',
            'name',
            ['dateStart', 'dateStart'],
            ['dateEnd', 'dateEnd'],
            'status',
            ['null', 'dateStartDate'],
            ['null', 'dateEndDate'],
            ['null', 'parentType'],
            ['null', 'parentId'],
            'createdAt',
        ];

        try {
            return $builder
                ->buildQueryBuilder()
                ->select($select)
                ->where([
                    'OR' => [
                        [
                            'dateEnd' => null,
                            'dateStart>=' => $from,
                            'dateStart<' => $to,
                        ],
                        [
                            'dateStart>=' => $from,
                            'dateStart<' => $to,
                        ],
                        [
                            'dateEnd>=' => $from,
                            'dateEnd<' => $to,
                        ],
                        [
                            'dateStart<=' => $from,
                            'dateEnd>=' => $to,
                        ],
                    ],
                ])
                ->build();
        } catch (BadRequest|Forbidden $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }
    }
}
