<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Acl;
use Espo\Core\Utils\Language;
use Espo\Core\Utils\Metadata;
use Espo\ORM\EntityManager;

/**
 * Single source of truth for CRM entity types that participate in Google Calendar export.
 */
class AllowedEntityTypesProvider
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
            if (!is_array($defs)) {
                continue;
            }

            if (isset($defs['fields']['saveToGoogleCalendar'])) {
                $entityTypes[$entityType] = true;
            }
        }

        $rows = $this->entityManager
            ->getRDBRepository('CalendarDateSource')
            ->select(['targetEntityType'])
            ->where(['deleted' => false])
            ->find();

        foreach ($rows as $row) {
            $targetEntityType = $row->get('targetEntityType');

            if (is_string($targetEntityType) && $targetEntityType !== '') {
                $entityTypes[$targetEntityType] = true;
            }
        }

        $list = array_keys($entityTypes);
        sort($list);

        return array_values(array_filter($list, function (string $entityType): bool {
            if (!$this->metadata->get(['scopes', $entityType, 'entity'])) {
                return false;
            }

            return $this->acl->checkScope($entityType);
        }));
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

    private function translateScopeName(string $entityType): string
    {
        $translated = $this->language->translate($entityType, 'scopeNames');

        if ($translated !== $entityType) {
            return $translated;
        }

        return $this->language->translate($entityType, 'scopeNames', 'Global') ?: $entityType;
    }
}
