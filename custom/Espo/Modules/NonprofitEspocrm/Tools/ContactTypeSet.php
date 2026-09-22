<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Entities\ArrayValue;
use Espo\ORM\Query\SelectBuilder;

/**
 * Contact / Lead contactType as a list: legal sets, Role names, ArrayValue where.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/api-search-params.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
 */
class ContactTypeSet
{
    public const OPTION_HELP_SEEKER = 'HelpSeeker';
    public const OPTION_COLLEAGUE = 'Colleague';
    public const OPTION_VOLUNTEER = 'Volunteer';
    public const OPTION_EMPLOYEE = 'Employee';
    public const OPTION_MEMBER = 'MemberContact';
    public const OPTION_ASSOCIATION_REP = 'AssociationRepresentative';
    public const OPTION_OTHER = 'Other';

    public const ROLE_VOLUNTEER = 'Volunteer';
    public const ROLE_EMPLOYEE = 'Employee';
    public const ROLE_MEMBER = 'Member';

    /** @var list<string> */
    public const OPTIONS = [
        self::OPTION_HELP_SEEKER,
        self::OPTION_COLLEAGUE,
        self::OPTION_VOLUNTEER,
        self::OPTION_EMPLOYEE,
        self::OPTION_MEMBER,
        self::OPTION_ASSOCIATION_REP,
        self::OPTION_OTHER,
    ];

    /** @var list<string> */
    public const PERSONNEL_OPTIONS = [
        self::OPTION_VOLUNTEER,
        self::OPTION_EMPLOYEE,
    ];

    /** @var list<string> */
    public const LEAD_OPTIONS = [
        self::OPTION_VOLUNTEER,
        self::OPTION_EMPLOYEE,
        self::OPTION_MEMBER,
        self::OPTION_OTHER,
    ];

    /** @var array<string, string> */
    public const TYPE_TO_ROLE = [
        self::OPTION_VOLUNTEER => self::ROLE_VOLUNTEER,
        self::OPTION_EMPLOYEE => self::ROLE_EMPLOYEE,
        self::OPTION_MEMBER => self::ROLE_MEMBER,
    ];

    /**
     * @return list<string>
     */
    public static function normalize(mixed $value): array
    {
        if (is_string($value)) {
            $value = trim($value);

            return $value === '' ? [] : [$value];
        }

        if (!is_array($value)) {
            return [];
        }

        $out = [];

        foreach ($value as $item) {
            if (!is_string($item)) {
                continue;
            }

            $item = trim($item);

            if ($item === '') {
                continue;
            }

            $out[] = $item;
        }

        return array_values(array_unique($out));
    }

    /**
     * Empty, one current option, or Volunteer+Member / Employee+Member.
     *
     * @param list<string> $types
     */
    public static function isLegal(array $types): bool
    {
        $types = array_values(array_unique($types));
        $n = count($types);

        if ($n === 0) {
            return true;
        }

        if ($n === 1) {
            return in_array($types[0], self::OPTIONS, true);
        }

        if ($n !== 2) {
            return false;
        }

        sort($types);

        return $types === [self::OPTION_EMPLOYEE, self::OPTION_MEMBER]
            || $types === [self::OPTION_MEMBER, self::OPTION_VOLUNTEER];
    }

    /**
     * @param list<string> $types
     */
    public static function hasPersonnel(array $types): bool
    {
        return in_array(self::OPTION_VOLUNTEER, $types, true)
            || in_array(self::OPTION_EMPLOYEE, $types, true);
    }

    /**
     * Volunteer / Employee / Member may create a CRM user (004.2 + 004.3).
     *
     * @param list<string> $types
     */
    public static function wantsCrmUser(array $types): bool
    {
        return self::hasPersonnel($types)
            || in_array(self::OPTION_MEMBER, $types, true);
    }

    /**
     * Convert / create-user checkbox default: on for Volunteer and Member, off for Generic.
     *
     * @param list<string> $types
     */
    public static function createUserDefaultOn(array $types): bool
    {
        return in_array(self::OPTION_VOLUNTEER, $types, true)
            || in_array(self::OPTION_MEMBER, $types, true);
    }

    /**
     * @param list<string> $types
     * @return list<string>
     */
    public static function roleNames(array $types): array
    {
        $out = [];

        foreach ($types as $type) {
            if (isset(self::TYPE_TO_ROLE[$type])) {
                $out[] = self::TYPE_TO_ROLE[$type];
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Personnel keys present in $new but not in $old.
     *
     * @param list<string> $new
     * @param list<string> $old
     * @return list<string>
     */
    public static function addedPersonnel(array $new, array $old): array
    {
        return array_values(array_diff(
            array_intersect($new, self::PERSONNEL_OPTIONS),
            array_intersect($old, self::PERSONNEL_OPTIONS)
        ));
    }

    /**
     * Primary-filter where: jsonArray contains $option via ArrayValue.
     *
     * @return array<string, mixed>
     */
    public static function containsWhere(string $entityType, string $option): array
    {
        $subQuery = SelectBuilder::create()
            ->select('entityId')
            ->from(ArrayValue::ENTITY_TYPE)
            ->where([
                'entityType' => $entityType,
                'attribute' => 'contactType',
                'value' => $option,
            ])
            ->build();

        return ['id=s' => $subQuery->getRaw()];
    }
}
