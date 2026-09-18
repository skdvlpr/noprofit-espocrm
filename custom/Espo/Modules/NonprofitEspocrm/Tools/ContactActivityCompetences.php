<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use PDO;
use Throwable;

/**
 * Contact is the stored competence list. Planner and copy command read/write here.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/entity-defs.md
 */
class ContactActivityCompetences
{
    /** @var list<string> */
    public const PERSONNEL_TYPES = ['Volunteer', 'Employee'];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * @return string[]
     */
    public static function normalizeList(mixed $value): array
    {
        if (!is_array($value)) {
            return [];
        }

        return array_values(array_filter($value, 'is_string'));
    }

    public static function isPersonnelType(?string $type): bool
    {
        return in_array((string) $type, self::PERSONNEL_TYPES, true);
    }

    /**
     * @return string[]
     */
    public function listForUser(Entity $user): array
    {
        $userId = (string) $user->getId();

        if ($userId === '') {
            return [];
        }

        $contact = $this->findPersonnelContact($userId);

        if (!$contact) {
            return [];
        }

        return self::normalizeList($contact->get('activityCompetences'));
    }

    /**
     * @return array{copied: int, skippedNoContact: int, skippedNotPersonnel: int, skippedEmpty: int}
     */
    public function copyFromUsers(iterable $users, bool $apply): array
    {
        $copied = 0;
        $skippedNoContact = 0;
        $skippedNotPersonnel = 0;
        $skippedEmpty = 0;

        foreach ($users as $user) {
            if (!$user instanceof Entity) {
                continue;
            }

            $result = $this->copyFromUser($user, $apply);

            if ($result === 'copied') {
                $copied++;
            } elseif ($result === 'no-contact') {
                $skippedNoContact++;
            } elseif ($result === 'skipped-empty') {
                $skippedEmpty++;
            } else {
                $skippedNotPersonnel++;
            }
        }

        return [
            'copied' => $copied,
            'skippedNoContact' => $skippedNoContact,
            'skippedNotPersonnel' => $skippedNotPersonnel,
            'skippedEmpty' => $skippedEmpty,
        ];
    }

    /**
     * @return 'copied'|'no-contact'|'skipped-type'|'skipped-empty'
     */
    public function copyFromUser(Entity $user, bool $apply): string
    {
        $list = self::normalizeList($user->get('activityCompetences'));
        $userId = (string) $user->getId();
        $contacts = $this->findLinkedContacts($userId);
        $leftover = null;

        $copied = false;
        $sawAny = false;
        $sawPersonnel = false;

        foreach ($contacts as $contact) {
            $sawAny = true;

            if (!self::isPersonnelType((string) ($contact->get('contactType') ?? ''))) {
                continue;
            }

            $sawPersonnel = true;
            $existing = self::normalizeList($contact->get('activityCompetences'));

            // Do not wipe a Contact list when the User ORM value is empty
            // (e.g. after the User field became notStorable).
            if ($list === [] && $existing !== []) {
                continue;
            }

            $write = $list;

            if ($write === []) {
                $leftover ??= $this->readLeftoverUserColumn($userId);
                $write = $leftover;

                if ($write === []) {
                    continue;
                }
            }

            if ($apply) {
                $contact->set('activityCompetences', $write);
                $this->entityManager->saveEntity($contact, [
                    SaveOption::SKIP_ALL => true,
                ]);
            }

            $copied = true;
        }

        if ($copied) {
            return 'copied';
        }

        if (!$sawAny) {
            return 'no-contact';
        }

        return $sawPersonnel ? 'skipped-empty' : 'skipped-type';
    }

    private function findPersonnelContact(string $userId): ?Entity
    {
        $fallback = null;

        foreach ($this->findLinkedContacts($userId) as $contact) {
            if (self::isPersonnelType((string) ($contact->get('contactType') ?? ''))) {
                return $contact;
            }

            $fallback ??= $contact;
        }

        return $fallback;
    }

    /**
     * After User activityCompetences is notStorable, ORM returns empty but a
     * leftover MariaDB column may still hold the pre-migration list. Read it
     * via entity-type → table mapping (no hardcoded table names) so production
     * can copy after this tree is deployed. Skip if the column is already gone
     * (hard rebuild). Cite:
     * https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
     *
     * @return string[]
     */
    private function readLeftoverUserColumn(string $userId): array
    {
        if ($userId === '') {
            return [];
        }

        try {
            $pdo = $this->entityManager->getPDO();
        } catch (Throwable) {
            return [];
        }

        $table = Util::toUnderScore('User');
        $column = Util::toUnderScore('activityCompetences');

        if (
            !is_string($table) ||
            !is_string($column) ||
            !preg_match('/^[a-z0-9_]+$/', $table) ||
            !preg_match('/^[a-z0-9_]+$/', $column)
        ) {
            return [];
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT `{$column}` FROM `{$table}` WHERE `id` = :id AND `deleted` = 0"
            );

            if ($stmt === false) {
                return [];
            }

            $stmt->bindValue(':id', $userId, PDO::PARAM_STR);
            $stmt->execute();
            $raw = $stmt->fetchColumn();
        } catch (Throwable) {
            return [];
        }

        if (!is_string($raw) || $raw === '') {
            return self::normalizeList($raw);
        }

        $decoded = json_decode($raw, true);

        return self::normalizeList(is_array($decoded) ? $decoded : null);
    }

    /**
     * @return iterable<Entity>
     */
    private function findLinkedContacts(string $userId): iterable
    {
        if ($userId === '') {
            return [];
        }

        return $this->entityManager
            ->getRDBRepository('Contact')
            ->where(['linkedUserId' => $userId])
            ->find();
    }
}
