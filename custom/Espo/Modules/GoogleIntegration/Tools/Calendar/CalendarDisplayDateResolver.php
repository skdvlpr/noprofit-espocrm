<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use DateTimeImmutable;
use DateTimeZone;
use Espo\Core\Field\DateTimeOptional;
use Espo\Core\ORM\Type\FieldType;
use Espo\Core\Utils\Config;
use Espo\ORM\Entity;

/**
 * Resolves Y-m-d as shown in the CRM record form (application timezone),
 * not the UTC calendar day from raw DB datetime strings.
 */
class CalendarDisplayDateResolver
{
    /** @var array<string, string> */
    private const DATETIME_COMPANION_DATE_FIELDS = [
        'dateStart' => 'dateStartDate',
        'dateEnd' => 'dateEndDate',
    ];

    public function __construct(
        private Config $config
    ) {}

    public function resolveDateOnly(Entity $entity, string $fieldName): ?string
    {
        if (!$entity->hasAttribute($fieldName)) {
            return null;
        }

        $companion = self::DATETIME_COMPANION_DATE_FIELDS[$fieldName] ?? null;

        if ($companion !== null && $entity->hasAttribute($companion)) {
            $companionValue = $entity->get($companion);

            if (is_string($companionValue) && strlen($companionValue) >= 10) {
                return substr($companionValue, 0, 10);
            }
        }

        $fieldType = $entity->getAttributeType($fieldName);

        if ($fieldType === FieldType::DATETIME_OPTIONAL) {
            $valueObject = $this->getDateTimeOptional($entity, $fieldName);

            if ($valueObject !== null) {
                return substr($valueObject->toString(), 0, 10);
            }
        }

        $raw = $entity->get($fieldName);

        if (!is_string($raw) || trim($raw) === '') {
            return null;
        }

        $raw = trim($raw);

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $raw)) {
            return $raw;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}\s+\d{2}:\d{2}:\d{2}$/', $raw)) {
            return $this->utcDateTimeToLocalDate($raw);
        }

        return null;
    }

    public function isDateTimeOptionalAllDay(Entity $entity, string $fieldName): bool
    {
        $valueObject = $this->getDateTimeOptional($entity, $fieldName);

        return $valueObject !== null && $valueObject->isAllDay();
    }

    private function getDateTimeOptional(Entity $entity, string $fieldName): ?DateTimeOptional
    {
        if ($entity->getAttributeType($fieldName) !== FieldType::DATETIME_OPTIONAL) {
            return null;
        }

        try {
            $valueObject = $entity->getValueObject($fieldName);
        } catch (\Throwable) {
            return null;
        }

        return $valueObject instanceof DateTimeOptional ? $valueObject : null;
    }

    private function utcDateTimeToLocalDate(string $utcDateTime): string
    {
        $parsed = DateTimeImmutable::createFromFormat(
            'Y-m-d H:i:s',
            $utcDateTime,
            new DateTimeZone('UTC')
        );

        if ($parsed === false) {
            return substr($utcDateTime, 0, 10);
        }

        $timezone = $this->config->get('timeZone') ?? 'UTC';

        return $parsed->setTimezone(new DateTimeZone($timezone))->format('Y-m-d');
    }
}
