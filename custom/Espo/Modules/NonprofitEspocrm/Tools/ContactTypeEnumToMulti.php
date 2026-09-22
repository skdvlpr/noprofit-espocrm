<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\ORM\Repository\Option\SaveOption;
use Espo\Core\Utils\Util;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use PDO;
use Throwable;

/**
 * One-shot copy of Contact.contactType from an enum string to a multiEnum list.
 *
 * Contact.contactType used to be an enum (varchar). After it becomes a multiEnum
 * (jsonArray + storeArrayValues) the ORM cannot decode a bare `Volunteer`
 * varchar as JSON and returns null. This helper reads the leftover raw column
 * via the entity-type → table mapping (no hardcoded table names), wraps the
 * string into a one-item list and saves through the ORM so the MultiEnum
 * field-processing saver populates ArrayValue rows.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
 */
class ContactTypeEnumToMulti
{
    public const ENTITY_TYPE = 'Contact';
    public const FIELD = 'contactType';

    public const STATE_EMPTY = 'empty';
    public const STATE_STRING = 'string';
    public const STATE_ARRAY = 'array';

    public const RESULT_COPIED = 'copied';
    public const RESULT_ALREADY_ARRAY = 'already-array';
    public const RESULT_EMPTY = 'empty';
    public const RESULT_FAILED = 'failed';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    /**
     * Classify a stored contactType value.
     *
     * - null / '' / non-scalar garbage → empty (target `[]`, nothing to write)
     * - plain option key string (e.g. `Volunteer`) → string (target `[key]`)
     * - list of strings, or a JSON string that decodes to one → array (already copied)
     *
     * Unexpected leftover strings are still wrapped as a one-item list; no merges
     * are invented.
     *
     * @return array{state: 'empty'|'string'|'array', list: string[]}
     */
    public static function classify(mixed $value): array
    {
        if (is_array($value)) {
            return [
                'state' => self::STATE_ARRAY,
                'list' => array_values(array_filter($value, 'is_string')),
            ];
        }

        if (!is_string($value)) {
            return ['state' => self::STATE_EMPTY, 'list' => []];
        }

        $value = trim($value);

        if ($value === '') {
            return ['state' => self::STATE_EMPTY, 'list' => []];
        }

        if (str_starts_with($value, '[')) {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return [
                    'state' => self::STATE_ARRAY,
                    'list' => array_values(array_filter($decoded, 'is_string')),
                ];
            }
        }

        return ['state' => self::STATE_STRING, 'list' => [$value]];
    }

    /**
     * Whether the ORM attribute is already a jsonArray (multiEnum metadata shipped
     * and rebuilt). Writing a list into a still-enum varchar attribute would be
     * mangled, so apply MUST be refused until this is true.
     */
    public function isFieldArray(): bool
    {
        try {
            $defs = $this->entityManager->getDefs()->getEntity(self::ENTITY_TYPE);

            if (!$defs->hasAttribute(self::FIELD)) {
                return false;
            }

            return $defs->getAttribute(self::FIELD)->getType() === Entity::JSON_ARRAY;
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * @return iterable<Entity>
     */
    public function findContacts(): iterable
    {
        return $this->entityManager
            ->getRDBRepository(self::ENTITY_TYPE)
            ->sth()
            ->find();
    }

    /**
     * @param iterable<Entity> $contacts
     * @return array{copied: int, skippedAlreadyArray: int, skippedEmpty: int, failed: int}
     */
    public function copyAll(iterable $contacts, bool $apply): array
    {
        $stats = [
            'copied' => 0,
            'skippedAlreadyArray' => 0,
            'skippedEmpty' => 0,
            'failed' => 0,
        ];

        foreach ($contacts as $contact) {
            if (!$contact instanceof Entity) {
                continue;
            }

            $result = $this->copyOne($contact, $apply);

            match ($result) {
                self::RESULT_COPIED => $stats['copied']++,
                self::RESULT_ALREADY_ARRAY => $stats['skippedAlreadyArray']++,
                self::RESULT_EMPTY => $stats['skippedEmpty']++,
                default => $stats['failed']++,
            };
        }

        return $stats;
    }

    /**
     * @return 'copied'|'already-array'|'empty'|'failed'
     */
    public function copyOne(Entity $contact, bool $apply): string
    {
        $id = (string) $contact->getId();

        if ($id === '') {
            return self::RESULT_FAILED;
        }

        // Prefer the raw column: after the attribute became jsonArray the ORM
        // yields null for a leftover varchar such as `Volunteer`.
        $value = $this->readLeftoverColumn($id) ?? $contact->get(self::FIELD);

        $classified = self::classify($value);

        if ($classified['state'] === self::STATE_ARRAY) {
            return self::RESULT_ALREADY_ARRAY;
        }

        if ($classified['state'] === self::STATE_EMPTY) {
            // Target is `[]`; NULL already reads as an empty list, nothing to write.
            return self::RESULT_EMPTY;
        }

        if (!$apply) {
            return self::RESULT_COPIED;
        }

        if (!$this->isFieldArray()) {
            return self::RESULT_FAILED;
        }

        try {
            $contact->set(self::FIELD, $classified['list']);

            // No SKIP_ALL: it skips afterSave, and the ArrayValue rows are
            // written by the core Common FieldProcessing afterSave hook
            // (MultiEnum saver). SILENT only drops stream notes,
            // notifications and webhooks.
            $this->entityManager->saveEntity($contact, [
                SaveOption::SILENT => true,
            ]);
        } catch (Throwable) {
            return self::RESULT_FAILED;
        }

        return self::RESULT_COPIED;
    }

    /**
     * Read the raw stored value of the contactType column for one Contact.
     * Table and column are derived from entity type / attribute names via
     * Util::toUnderScore; no SQL identifiers are hardcoded. Returns null when
     * the column is NULL, gone, or unreadable so the caller falls back to ORM.
     */
    private function readLeftoverColumn(string $id): ?string
    {
        try {
            $pdo = $this->entityManager->getPDO();
        } catch (Throwable) {
            return null;
        }

        $table = Util::toUnderScore(self::ENTITY_TYPE);
        $column = Util::toUnderScore(self::FIELD);

        if (
            !is_string($table) ||
            !is_string($column) ||
            !preg_match('/^[a-z0-9_]+$/', $table) ||
            !preg_match('/^[a-z0-9_]+$/', $column)
        ) {
            return null;
        }

        try {
            $stmt = $pdo->prepare(
                "SELECT `{$column}` FROM `{$table}` WHERE `id` = :id AND `deleted` = 0"
            );

            if ($stmt === false) {
                return null;
            }

            $stmt->bindValue(':id', $id, PDO::PARAM_STR);
            $stmt->execute();
            $raw = $stmt->fetchColumn();
        } catch (Throwable) {
            return null;
        }

        return is_string($raw) ? $raw : null;
    }
}
