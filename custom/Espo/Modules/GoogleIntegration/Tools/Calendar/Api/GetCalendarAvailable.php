<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar\Api;

use Espo\Core\Acl;
use Espo\Core\Api\Action;
use Espo\Core\Api\Request;
use Espo\Core\Api\Response;
use Espo\Core\Api\ResponseComposer;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\Select;
use Espo\ORM\Query\UnionBuilder;
use PDO;
use RuntimeException;
use Throwable;

/**
 * Calendar events the current user may read by ACL (not limited to assigned/attendee "my" set).
 */
class GetCalendarAvailable implements Action
{
    private const MAX_CALENDAR_RANGE = 123;

    public function __construct(
        private Acl $acl,
        private Config $config,
        private Metadata $metadata,
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
    ) {}

    public function process(Request $request): Response
    {
        if (!$this->acl->check('Calendar')) {
            throw new Forbidden();
        }

        $from = $request->getQueryParam('from');
        $to = $request->getQueryParam('to');

        if (empty($from) || empty($to)) {
            throw new BadRequest();
        }

        if (strtotime((string) $to) - strtotime((string) $from) > self::MAX_CALENDAR_RANGE * 24 * 3600) {
            throw new Forbidden('Too long range.');
        }

        $scopeList = null;

        if ($request->getQueryParam('scopeList') !== null) {
            $scopeList = array_values(array_filter(
                explode(',', (string) $request->getQueryParam('scopeList')),
                static fn (string $s): bool => $s !== ''
            ));
        }

        $calendarEntityList = $this->config->get('calendarEntityList', []) ?? [];

        if (!is_array($calendarEntityList)) {
            $calendarEntityList = [];
        }

        if ($scopeList === null) {
            $scopeList = $calendarEntityList;
        }

        $queryList = [];

        foreach ($scopeList as $scope) {
            if (!in_array($scope, $calendarEntityList, true)) {
                continue;
            }

            if ($scope === 'GoogleCalendarOverlayEvent') {
                continue;
            }

            if (!$this->acl->checkScope($scope)) {
                continue;
            }

            if (!$this->metadata->get(['scopes', $scope, 'calendar'])) {
                continue;
            }

            try {
                $queryList[] = $this->buildAclOnlyQuery($scope, (string) $from, (string) $to);
            } catch (Throwable) {
                // Skip scopes that cannot be queried.
            }
        }

        if ($queryList === []) {
            return ResponseComposer::json([]);
        }

        /** @var UnionBuilder $builder */
        $builder = $this->entityManager->getQueryBuilder()->union();

        foreach ($queryList as $query) {
            $builder->query($query);
        }

        $sth = $this->entityManager->getQueryExecutor()->execute($builder->build());
        $rowList = $sth->fetchAll(PDO::FETCH_ASSOC) ?: [];

        $list = [];

        foreach ($rowList as $row) {
            $list[] = (object) [
                'id' => $row['id'] ?? null,
                'scope' => isset($row['scope']) ? trim((string) $row['scope'], '"') : null,
                'name' => $row['name'] ?? null,
                'dateStart' => $row['dateStart'] ?? null,
                'dateEnd' => $row['dateEnd'] ?? null,
                'dateStartDate' => $row['dateStartDate'] ?? null,
                'dateEndDate' => $row['dateEndDate'] ?? null,
                'status' => $row['status'] ?? null,
                'color' => $row['color'] ?? null,
            ];
        }

        return ResponseComposer::json($list);
    }

    private function buildAclOnlyQuery(string $scope, string $from, string $to): Select
    {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from($scope)
            ->withStrictAccessControl();

        $seed = $this->entityManager->getNewEntity($scope);

        $select = [
            ['"' . $scope . '"', 'scope'],
            'id',
            'name',
            $seed->hasAttribute('dateStart') ? ['dateStart', 'dateStart'] : ['null', 'dateStart'],
            $seed->hasAttribute('dateEnd') ? ['dateEnd', 'dateEnd'] : ['null', 'dateEnd'],
            $seed->hasAttribute('status') ? ['status', 'status'] : ['null', 'status'],
            $seed->hasAttribute('dateStartDate') ? ['dateStartDate', 'dateStartDate'] : ['null', 'dateStartDate'],
            $seed->hasAttribute('dateEndDate') ? ['dateEndDate', 'dateEndDate'] : ['null', 'dateEndDate'],
            $seed->hasAttribute('parentType') ? ['parentType', 'parentType'] : ['null', 'parentType'],
            $seed->hasAttribute('parentId') ? ['parentId', 'parentId'] : ['null', 'parentId'],
            'createdAt',
        ];

        $additionalAttributeList = $this->metadata->get(['app', 'calendar', 'additionalAttributeList']) ?? [];

        foreach ($additionalAttributeList as $attribute) {
            $select[] = $seed->hasAttribute($attribute)
                ? [$attribute, $attribute]
                : ['null', $attribute];
        }

        try {
            $queryBuilder = $builder->buildQueryBuilder();
        } catch (Throwable $e) {
            throw new RuntimeException($e->getMessage(), 0, $e);
        }

        $or = [
            [
                'dateStart>=' => $from,
                'dateStart<' => $to,
            ],
        ];

        if ($seed->hasAttribute('dateEnd')) {
            $or[] = [
                'dateEnd>=' => $from,
                'dateEnd<' => $to,
            ];
            $or[] = [
                'dateStart<=' => $from,
                'dateEnd>=' => $to,
            ];
            $or[] = [
                'dateEnd' => null,
                'dateStart>=' => $from,
                'dateStart<' => $to,
            ];
        }

        if ($seed->hasAttribute('dateEndDate')) {
            $or[] = [
                'dateEndDate!=' => null,
                'dateEndDate>=' => substr($from, 0, 10),
                'dateEndDate<' => substr($to, 0, 10),
            ];
        }

        return $queryBuilder
            ->select($select)
            ->where(['OR' => $or])
            ->limit(0, 500)
            ->build();
    }
}
