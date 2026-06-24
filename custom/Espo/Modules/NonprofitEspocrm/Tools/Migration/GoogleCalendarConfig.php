<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Migration;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;

/**
 * Import CalendarTemplate + CalendarDateSource rows from export-google-calendar-config.php.
 */
class GoogleCalendarConfig
{
    public const FORMAT_VERSION = 1;

    /** @var string[] */
    private const SKIP_FIELDS = [
        'id',
        'createdAt',
        'modifiedAt',
        'createdById',
        'modifiedById',
        'createdByName',
        'modifiedByName',
        'deleted',
    ];

    /**
     * @param array<string, mixed> $payload
     * @return array{templates: array<string, string>, dateSources: array<string, string>}
     */
    public static function apply(EntityManager $em, array $payload): array
    {
        if (($payload['version'] ?? null) !== self::FORMAT_VERSION) {
            throw new \InvalidArgumentException('Unsupported google calendar config version.');
        }

        $templateIdMap = [];
        $templateReport = [];

        foreach ($payload['calendarTemplates'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $oldId = is_string($row['id'] ?? null) ? $row['id'] : null;
            $entity = self::findTemplate($em, $row) ?? $em->getRDBRepository('CalendarTemplate')->getNew();
            $status = $entity->isNew() ? 'created' : 'updated';
            self::fillEntity($entity, $row);
            $em->saveEntity($entity);
            $key = self::templateKey($row);
            $templateReport[$key] = $status;
            if ($oldId !== null) {
                $templateIdMap[$oldId] = $entity->getId();
            }
        }

        $dateSourceReport = [];
        foreach ($payload['calendarDateSources'] ?? [] as $row) {
            if (!is_array($row)) {
                continue;
            }
            $entity = self::findDateSource($em, $row) ?? $em->getRDBRepository('CalendarDateSource')->getNew();
            $status = $entity->isNew() ? 'created' : 'updated';
            $attributes = $row;
            $oldTemplateId = is_string($attributes['defaultTemplateId'] ?? null) ? $attributes['defaultTemplateId'] : null;
            if ($oldTemplateId !== null && isset($templateIdMap[$oldTemplateId])) {
                $attributes['defaultTemplateId'] = $templateIdMap[$oldTemplateId];
            }
            self::fillEntity($entity, $attributes);
            $em->saveEntity($entity);
            $dateSourceReport[self::dateSourceKey($row)] = $status;
        }

        return ['templates' => $templateReport, 'dateSources' => $dateSourceReport];
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function findTemplate(EntityManager $em, array $row): ?Entity
    {
        $name = $row['name'] ?? null;
        $target = $row['targetEntityType'] ?? null;
        if (!is_string($name) || !is_string($target)) {
            return null;
        }

        return $em->getRDBRepository('CalendarTemplate')
            ->where([
                'name'               => $name,
                'targetEntityType'   => $target,
                'deleted'            => false,
            ])
            ->findOne();
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function findDateSource(EntityManager $em, array $row): ?Entity
    {
        $target = $row['targetEntityType'] ?? null;
        $sourceDateType = $row['sourceDateType'] ?? null;
        if (!is_string($target) || !is_string($sourceDateType)) {
            return null;
        }

        return $em->getRDBRepository('CalendarDateSource')
            ->where([
                'targetEntityType' => $target,
                'sourceDateType'   => $sourceDateType,
                'deleted'          => false,
            ])
            ->findOne();
    }

    /**
     * @param array<string, mixed> $row
     */
    private static function fillEntity(Entity $entity, array $row): void
    {
        foreach ($row as $field => $value) {
            if (in_array($field, self::SKIP_FIELDS, true)) {
                continue;
            }
            if (str_ends_with($field, 'Name')) {
                continue;
            }
            if (str_ends_with($field, 'Id') && $field !== 'defaultTemplateId') {
                continue;
            }
            $entity->set($field, $value);
        }
    }

    /** @param array<string, mixed> $row */
    private static function templateKey(array $row): string
    {
        return ($row['targetEntityType'] ?? '?') . ':' . ($row['name'] ?? '?');
    }

    /** @param array<string, mixed> $row */
    private static function dateSourceKey(array $row): string
    {
        return ($row['targetEntityType'] ?? '?') . ':' . ($row['sourceDateType'] ?? '?');
    }
}
