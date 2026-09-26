<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Entities\EmailAddress;
use Espo\Entities\PhoneNumber;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Repositories\EmailAddress as EmailAddressRepository;
use Espo\Repositories\PhoneNumber as PhoneNumberRepository;
use stdClass;

/**
 * Copy name (prefix, first, last) and full email/phone sets between a
 * linked Volunteer, Employee, or Associato Contact and User.
 * MUST NOT create records or touch assignedUserId / userName.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
 */
class ContactUserChannelSync
{
    public const SKIP_OPTION = 'nonprofitSkipContactUserChannelSync';

    /**
     * Person-name parts. The prefix attribute is salutationName
     * (salutation + field name), not salutation.
     *
     * @var list<string>
     */
    private const NAME_FIELDS = ['salutationName', 'firstName', 'lastName'];

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterContactSave(Entity $contact, SaveOptions $options): void
    {
        if ($this->shouldSkip($options)) {
            return;
        }

        if (!$this->isCrmUserContact($contact)) {
            return;
        }

        $userId = trim((string) ($contact->get('linkedUserId') ?? ''));

        if ($userId === '') {
            return;
        }

        if (!$this->identityAttributesChanged($contact)) {
            return;
        }

        $user = $this->entityManager->getEntityById('User', $userId);

        if (!$this->isSyncableUser($user)) {
            return;
        }

        $this->copyIfDifferent($contact, $user);
    }

    public function afterUserSave(Entity $user, SaveOptions $options): void
    {
        if ($this->shouldSkip($options)) {
            return;
        }

        if (!$this->isSyncableUser($user)) {
            return;
        }

        if (!$this->identityAttributesChanged($user)) {
            return;
        }

        $userId = trim((string) $user->getId());

        if ($userId === '') {
            return;
        }

        $contact = $this->entityManager
            ->getRDBRepository('Contact')
            ->where(['linkedUserId' => $userId])
            ->findOne();

        if (!$contact) {
            return;
        }

        if (!$this->isCrmUserContact($contact)) {
            return;
        }

        $this->copyIfDifferent($user, $contact);
    }

    private function shouldSkip(SaveOptions $options): bool
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return true;
        }

        return (bool) $options->get(self::SKIP_OPTION);
    }

    private function isCrmUserContact(Entity $contact): bool
    {
        return ContactTypeSet::wantsCrmUser(
            ContactTypeSet::normalize($contact->get('contactType'))
        );
    }

    private function isSyncableUser(?Entity $user): bool
    {
        if (!$user) {
            return false;
        }

        $type = (string) ($user->get('type') ?? '');

        return !in_array($type, ['portal', 'system', 'api'], true);
    }

    private function identityAttributesChanged(Entity $entity): bool
    {
        if ($entity->isNew()) {
            return true;
        }

        if (
            $entity->isAttributeChanged('emailAddressData')
            || $entity->isAttributeChanged('phoneNumberData')
            || $entity->isAttributeChanged('emailAddress')
            || $entity->isAttributeChanged('phoneNumber')
        ) {
            return true;
        }

        foreach (self::NAME_FIELDS as $field) {
            if ($entity->isAttributeChanged($field)) {
                return true;
            }
        }

        return false;
    }

    private function copyIfDifferent(Entity $source, Entity $target): void
    {
        $dirty = false;
        $sourceEmails = $this->readEmailSet($source);
        $sourcePhones = $this->readPhoneSet($source);
        $targetEmails = $this->readEmailSet($target);
        $targetPhones = $this->readPhoneSet($target);

        if (
            $sourceEmails !== []
            && $this->emailFingerprint($sourceEmails) !== $this->emailFingerprint($targetEmails)
        ) {
            $target->set('emailAddressData', $sourceEmails);
            $dirty = true;
        }

        if ($this->phoneFingerprint($sourcePhones) !== $this->phoneFingerprint($targetPhones)) {
            $target->set('phoneNumberData', $sourcePhones);
            $dirty = true;
        }

        foreach (self::NAME_FIELDS as $field) {
            $from = $this->nameValue($source, $field);
            $to = $this->nameValue($target, $field);

            if ($from === $to) {
                continue;
            }

            $target->set($field, $from === '' ? null : $from);
            $dirty = true;
        }

        if (!$dirty) {
            return;
        }

        $this->entityManager->saveEntity($target, [
            self::SKIP_OPTION => true,
        ]);
    }

    private function nameValue(Entity $entity, string $field): string
    {
        return trim((string) ($entity->get($field) ?? ''));
    }

    /**
     * @return list<stdClass>
     */
    private function readEmailSet(Entity $entity): array
    {
        $raw = $entity->get('emailAddressData');
        $explicit = is_array($raw);

        if (!$explicit) {
            $raw = $this->loadEmailRows($entity);
        }

        $out = $this->emailRowsFrom($raw);

        if ($out !== [] || $explicit) {
            return $out;
        }

        $primary = trim((string) ($entity->get('emailAddress') ?? ''));

        if ($primary === '') {
            return [];
        }

        return [$this->emailRow($primary, true)];
    }

    /**
     * @return list<stdClass>
     */
    private function readPhoneSet(Entity $entity): array
    {
        $raw = $entity->get('phoneNumberData');
        $explicit = is_array($raw);

        if (!$explicit) {
            $raw = $this->loadPhoneRows($entity);
        }

        $out = $this->phoneRowsFrom($raw);

        if ($out !== [] || $explicit) {
            return $out;
        }

        $primary = trim((string) ($entity->get('phoneNumber') ?? ''));

        if ($primary === '') {
            return [];
        }

        return [$this->phoneRow($primary, true, '')];
    }

    /**
     * @return list<stdClass>
     */
    private function emailRowsFrom(mixed $raw): array
    {
        $out = [];

        foreach ($this->normalizeRows($raw) as $row) {
            $normalized = $this->normalizeEmailRow($row);

            if ($normalized) {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * @return list<stdClass>
     */
    private function phoneRowsFrom(mixed $raw): array
    {
        $out = [];

        foreach ($this->normalizeRows($raw) as $row) {
            $normalized = $this->normalizePhoneRow($row);

            if ($normalized) {
                $out[] = $normalized;
            }
        }

        return $out;
    }

    /**
     * getEntityById / findOne do not run the email or phone field loaders.
     * An unloaded set is null, which compared equal to a real empty set and
     * skipped the write. A later name save then copied the stale numbers back.
     *
     * @return list<stdClass>
     */
    private function loadEmailRows(Entity $entity): array
    {
        $repository = $this->entityManager->getRepository(EmailAddress::ENTITY_TYPE);

        if (!$repository instanceof EmailAddressRepository) {
            return [];
        }

        $rows = $repository->getEmailAddressData($entity);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<stdClass>
     */
    private function loadPhoneRows(Entity $entity): array
    {
        $repository = $this->entityManager->getRepository(PhoneNumber::ENTITY_TYPE);

        if (!$repository instanceof PhoneNumberRepository) {
            return [];
        }

        $rows = $repository->getPhoneNumberData($entity);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return list<mixed>
     */
    private function normalizeRows(mixed $raw): array
    {
        if (!is_array($raw)) {
            return [];
        }

        return array_values($raw);
    }

    private function normalizeEmailRow(mixed $row): ?stdClass
    {
        if (is_array($row)) {
            $row = (object) $row;
        }

        if (!$row instanceof stdClass) {
            return null;
        }

        $address = trim((string) ($row->emailAddress ?? ''));

        if ($address === '') {
            return null;
        }

        return $this->emailRow(
            $address,
            !empty($row->primary),
            !empty($row->optOut),
            !empty($row->invalid)
        );
    }

    private function normalizePhoneRow(mixed $row): ?stdClass
    {
        if (is_array($row)) {
            $row = (object) $row;
        }

        if (!$row instanceof stdClass) {
            return null;
        }

        $number = trim((string) ($row->phoneNumber ?? ''));

        if ($number === '') {
            return null;
        }

        return $this->phoneRow(
            $number,
            !empty($row->primary),
            trim((string) ($row->type ?? '')),
            !empty($row->optOut),
            !empty($row->invalid)
        );
    }

    private function emailRow(
        string $address,
        bool $primary,
        bool $optOut = false,
        bool $invalid = false
    ): stdClass {
        $row = new stdClass();
        $row->emailAddress = $address;
        $row->primary = $primary;
        $row->optOut = $optOut;
        $row->invalid = $invalid;

        return $row;
    }

    private function phoneRow(
        string $number,
        bool $primary,
        string $type,
        bool $optOut = false,
        bool $invalid = false
    ): stdClass {
        $row = new stdClass();
        $row->phoneNumber = $number;
        $row->primary = $primary;
        $row->type = $type;
        $row->optOut = $optOut;
        $row->invalid = $invalid;

        return $row;
    }

    /**
     * @param list<stdClass> $rows
     */
    private function emailFingerprint(array $rows): string
    {
        $parts = [];

        foreach ($rows as $row) {
            $parts[] = strtolower((string) $row->emailAddress)
                . '|' . ($row->primary ? '1' : '0')
                . '|' . ($row->optOut ? '1' : '0')
                . '|' . ($row->invalid ? '1' : '0');
        }

        sort($parts);

        return implode("\n", $parts);
    }

    /**
     * @param list<stdClass> $rows
     */
    private function phoneFingerprint(array $rows): string
    {
        $parts = [];

        foreach ($rows as $row) {
            $parts[] = trim((string) $row->phoneNumber)
                . '|' . strtolower((string) ($row->type ?? ''))
                . '|' . ($row->primary ? '1' : '0')
                . '|' . ($row->optOut ? '1' : '0')
                . '|' . ($row->invalid ? '1' : '0');
        }

        sort($parts);

        return implode("\n", $parts);
    }
}
