<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Acl;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

/**
 * CRM entity types that expose at least one date/datetime field (for CalendarDateSource / CalendarTemplate).
 */
class DateCapableEntityTypesProvider
{
    /** @var list<string> */
    private const AUDIT_DATE_FIELDS = [
        'createdAt',
        'modifiedAt',
        'deletedAt',
    ];

    public function __construct(
        private Metadata $metadata,
        private EntityManager $entityManager,
        private Acl $acl,
        private EntityScopeNameTranslator $entityScopeNameTranslator
    ) {}

    /**
     * @return list<string>
     */
    public function getEntityTypeList(): array
    {
        $entityTypes = [];

        foreach ($this->metadata->get('entityDefs', []) as $entityType => $defs) {
            if (!is_string($entityType) || $entityType === '' || !is_array($defs)) {
                continue;
            }

            if (!$this->isEligibleEntityType($entityType, $defs)) {
                continue;
            }

            $entityTypes[$entityType] = true;
        }

        $rows = $this->entityManager
            ->getRDBRepository('CalendarDateSource')
            ->select(['targetEntityType'])
            ->where(['deleted' => false])
            ->find();

        foreach ($rows as $row) {
            $targetEntityType = $row->get('targetEntityType');

            if (!is_string($targetEntityType) || $targetEntityType === '') {
                continue;
            }

            if (!$this->metadata->get(['scopes', $targetEntityType, 'entity'])) {
                continue;
            }

            if (!$this->acl->checkScope($targetEntityType)) {
                continue;
            }

            $entityTypes[$targetEntityType] = true;
        }

        $templateRows = $this->entityManager
            ->getRDBRepository('CalendarTemplate')
            ->select(['targetEntityType'])
            ->where(['deleted' => false])
            ->find();

        foreach ($templateRows as $row) {
            $targetEntityType = $row->get('targetEntityType');

            if (!is_string($targetEntityType) || $targetEntityType === '') {
                continue;
            }

            if (!$this->metadata->get(['scopes', $targetEntityType, 'entity'])) {
                continue;
            }

            if (!$this->acl->checkScope($targetEntityType)) {
                continue;
            }

            $entityTypes[$targetEntityType] = true;
        }

        $list = array_keys($entityTypes);

        usort($list, function (string $a, string $b): int {
            return strcasecmp(
                $this->entityScopeNameTranslator->translate($a) ?? $a,
                $this->entityScopeNameTranslator->translate($b) ?? $b
            );
        });

        return $list;
    }

    /**
     * @return list<array{entityType: string, label: string}>
     */
    public function getReadableList(): array
    {
        $list = [];

        foreach ($this->getEntityTypeList() as $entityType) {
            $label = $this->entityScopeNameTranslator->translate($entityType);

            if ($label === null) {
                continue;
            }

            $list[] = [
                'entityType' => $entityType,
                'label' => $label,
            ];
        }

        return $list;
    }

    /**
     * @param array<string, mixed> $defs
     */
    private function isEligibleEntityType(string $entityType, array $defs): bool
    {
        if (!$this->metadata->get(['scopes', $entityType, 'entity'])) {
            return false;
        }

        if (!$this->acl->checkScope($entityType)) {
            return false;
        }

        if (!$this->hasSelectableDateField($defs)) {
            return false;
        }

        if (isset($defs['fields']['saveToGoogleCalendar'])) {
            return true;
        }

        if ($this->metadata->get(['scopes', $entityType, 'calendar'])) {
            return true;
        }

        return $this->isUserFacingObjectScope($entityType);
    }

    private function isUserFacingObjectScope(string $entityType): bool
    {
        $scopes = $this->metadata->get(['scopes', $entityType]);

        if (!is_array($scopes)) {
            return false;
        }

        return ($scopes['tab'] ?? false) === true
            && ($scopes['layouts'] ?? false) === true
            && ($scopes['object'] ?? false) === true;
    }

    /**
     * @param array<string, mixed> $defs
     */
    private function hasSelectableDateField(array $defs): bool
    {
        return $this->getSelectableDateFieldList($defs) !== [];
    }

    /**
     * @param array<string, mixed> $defs
     * @return list<string>
     */
    private function getSelectableDateFieldList(array $defs): array
    {
        $fields = $defs['fields'] ?? [];

        if (!is_array($fields)) {
            return [];
        }

        $business = [];
        $fallback = [];

        foreach ($fields as $name => $fieldDef) {
            if (!is_string($name) || $name === '' || !is_array($fieldDef)) {
                continue;
            }

            $type = $fieldDef['type'] ?? null;

            if ($type !== 'date' && $type !== 'datetime') {
                continue;
            }

            if (in_array($name, self::AUDIT_DATE_FIELDS, true)) {
                $fallback[] = $name;

                continue;
            }

            $business[] = $name;
        }

        $list = $business !== [] ? $business : $fallback;

        sort($list);

        return $list;
    }

}
