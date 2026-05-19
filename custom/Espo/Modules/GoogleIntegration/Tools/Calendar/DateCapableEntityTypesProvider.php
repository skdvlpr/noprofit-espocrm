<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Acl;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

/**
 * CRM entity types that expose at least one date/datetime field (for CalendarDateSource / CalendarTemplate).
 */
class DateCapableEntityTypesProvider
{
    public function __construct(
        private Metadata $metadata,
        private EntityManager $entityManager,
        private Acl $acl,
        private Language $language
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
            return strcasecmp($this->translateScopeName($a), $this->translateScopeName($b));
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
            $list[] = [
                'entityType' => $entityType,
                'label' => $this->translateScopeName($entityType),
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

        return $this->hasDateOrDatetimeField($defs);
    }

    /**
     * @param array<string, mixed> $defs
     */
    private function hasDateOrDatetimeField(array $defs): bool
    {
        $fields = $defs['fields'] ?? [];

        if (!is_array($fields)) {
            return false;
        }

        foreach ($fields as $fieldDef) {
            if (!is_array($fieldDef)) {
                continue;
            }

            $type = $fieldDef['type'] ?? null;

            if ($type === 'date' || $type === 'datetime') {
                return true;
            }
        }

        return false;
    }

    private function translateScopeName(string $entityType): string
    {
        $translated = $this->language->translate($entityType, 'scopeNames');

        if ($translated !== $entityType) {
            return $translated;
        }

        return $this->language->translate($entityType, 'scopeNames', 'Global') ?: $entityType;
    }
}
