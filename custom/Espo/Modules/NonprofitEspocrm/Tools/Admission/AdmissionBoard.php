<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Admission;

use Espo\Core\Exceptions\Forbidden;
use Espo\ORM\Entity;

/**
 * Board section: admin, or a user whose Role name is Member.
 * An enum stores one outcome and one fee answer.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
 */
class AdmissionBoard
{
    public const ROLE_MEMBER = 'Member';

    /** @var list<string> */
    public const FIELDS = [
        'admissionBoardDate',
        'admissionOutcome',
        'memberBookNumber',
        'admissionFeePaid',
        'admissionReceiptNumber',
    ];

    /**
     * @param list<string> $roleNames
     */
    public static function mayEdit(bool $logged, bool $admin, array $roleNames): bool
    {
        if (!$logged) {
            return true;
        }

        if ($admin) {
            return true;
        }

        return in_array(self::ROLE_MEMBER, $roleNames, true);
    }

    public static function isChanged(Entity $entity): bool
    {
        foreach (self::FIELDS as $field) {
            if (!$entity->isAttributeChanged($field)) {
                continue;
            }

            $value = $entity->get($field);
            $empty = $value === null || $value === '' || $value === [];

            if (!$empty) {
                return true;
            }

            $fetched = $entity->getFetched($field);
            $fetchedEmpty = $fetched === null || $fetched === '' || $fetched === [];

            if (!$fetchedEmpty) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<string> $roleNames
     */
    public static function assertMaySave(Entity $entity, bool $logged, bool $admin, array $roleNames): void
    {
        if (!self::isChanged($entity)) {
            return;
        }

        if (self::mayEdit($logged, $admin, $roleNames)) {
            return;
        }

        throw new Forbidden("Only an administrator or a Member may fill the admission board section.");
    }
}
