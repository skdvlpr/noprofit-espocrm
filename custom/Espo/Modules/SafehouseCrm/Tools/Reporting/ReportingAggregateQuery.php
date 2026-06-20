<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting;

use Espo\Core\Acl;
use Espo\Core\Acl\Table;
use Espo\Core\Exceptions\Forbidden;
use Espo\Core\Select\SearchParams;
use Espo\Core\Select\SelectBuilderFactory;
use Espo\ORM\EntityManager;
use Espo\ORM\Query\SelectBuilder;

/**
 * DB-level SUM / GROUP BY for reporting entities (PERF-002 — no full-table PHP loops).
 */
class ReportingAggregateQuery
{
    public function __construct(
        private EntityManager $entityManager,
        private SelectBuilderFactory $selectBuilderFactory,
        private Acl $acl,
    ) {}

    /**
     * @param string[] $sumAttributes
     * @return array<string, float>
     */
    public function sum(
        string $entityType,
        array $sumAttributes,
        ?SearchParams $searchParams = null,
        ?array $additionalWhere = null,
    ): array {
        $this->assertCanSum($entityType, $sumAttributes);

        $queryBuilder = $this->buildBaseQueryBuilder($entityType, $searchParams, $additionalWhere);

        $select = [];

        foreach ($sumAttributes as $attribute) {
            $select[] = ['SUM:' . $attribute, $attribute];
        }

        $queryBuilder->select($select);

        return $this->fetchSingleSumRow($queryBuilder, $sumAttributes);
    }

    /**
     * @param string[] $sumAttributes
     * @param string[] $groupByAttributes
     * @return array<int, array<string, mixed>>
     */
    public function sumGrouped(
        string $entityType,
        array $sumAttributes,
        array $groupByAttributes,
        ?SearchParams $searchParams = null,
        ?array $additionalWhere = null,
    ): array {
        $this->assertCanSum($entityType, $sumAttributes);

        if ($groupByAttributes === []) {
            throw new \InvalidArgumentException('groupByAttributes must not be empty.');
        }

        $queryBuilder = $this->buildBaseQueryBuilder($entityType, $searchParams, $additionalWhere);

        $select = [];

        foreach ($groupByAttributes as $groupAttribute) {
            $select[] = $groupAttribute;
        }

        foreach ($sumAttributes as $attribute) {
            $select[] = ['SUM:' . $attribute, $attribute];
        }

        $queryBuilder
            ->select($select)
            ->group($groupByAttributes);

        $sth = $this->entityManager->getQueryExecutor()->execute($queryBuilder->build());

        $rows = [];

        while ($row = $sth->fetch()) {
            $normalized = [];

            foreach ($groupByAttributes as $groupAttribute) {
                $normalized[$groupAttribute] = $row[$groupAttribute] ?? null;
            }

            foreach ($sumAttributes as $attribute) {
                $normalized[$attribute] = $this->castNumber($row[$attribute] ?? 0);
            }

            $rows[] = $normalized;
        }

        return $rows;
    }

    /**
     * @param string[] $sumAttributes
     */
    private function assertCanSum(string $entityType, array $sumAttributes): void
    {
        if (!$this->acl->check($entityType, Table::ACTION_READ)) {
            throw new Forbidden("No read access to $entityType.");
        }

        foreach ($sumAttributes as $attribute) {
            if (!$this->acl->checkField($entityType, $attribute, Table::ACTION_READ)) {
                throw new Forbidden("No read access to $entityType.$attribute.");
            }
        }
    }

    private function buildBaseQueryBuilder(
        string $entityType,
        ?SearchParams $searchParams,
        ?array $additionalWhere,
    ): SelectBuilder {
        $builder = $this->selectBuilderFactory
            ->create()
            ->from($entityType)
            ->withStrictAccessControl();

        if ($searchParams !== null) {
            $builder->withSearchParams($searchParams);
        }

        $queryBuilder = $builder->buildQueryBuilder();

        if ($additionalWhere !== null && $additionalWhere !== []) {
            $queryBuilder->where($additionalWhere);
        }

        return $queryBuilder;
    }

    /**
     * @param string[] $sumAttributes
     * @return array<string, float>
     */
    private function fetchSingleSumRow(SelectBuilder $queryBuilder, array $sumAttributes): array
    {
        $sth = $this->entityManager->getQueryExecutor()->execute($queryBuilder->build());
        $row = $sth->fetch() ?: [];

        $result = [];

        foreach ($sumAttributes as $attribute) {
            $result[$attribute] = $this->castNumber($row[$attribute] ?? 0);
        }

        return $result;
    }

    private function castNumber(mixed $value): float
    {
        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        if (!is_string($value)) {
            return 0.0;
        }

        if (str_contains($value, '.')) {
            return (float) $value;
        }

        return (float) (int) $value;
    }
}
