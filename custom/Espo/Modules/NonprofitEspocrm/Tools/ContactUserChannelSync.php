<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use stdClass;

/**
 * Copy full emailAddressData / phoneNumberData between linked Volunteer or
 * Employee Contact and User. MUST NOT create records or touch assignedUserId.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/orm.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/coding-practices.md
 */
class ContactUserChannelSync
{
    public const SKIP_OPTION = 'nonprofitSkipContactUserChannelSync';

    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function afterContactSave(Entity $contact, SaveOptions $options): void
    {
        if ($this->shouldSkip($options)) {
            return;
        }

        if (!$this->isPersonnelContact($contact)) {
            return;
        }

        $userId = trim((string) ($contact->get('linkedUserId') ?? ''));

        if ($userId === '') {
            return;
        }

        if (!$this->channelAttributesChanged($contact)) {
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

        if (!$this->channelAttributesChanged($user)) {
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

        if (!$this->isPersonnelContact($contact)) {
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

    private function isPersonnelContact(Entity $contact): bool
    {
        return ContactTypeSet::hasPersonnel(
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

    private function channelAttributesChanged(Entity $entity): bool
    {
        if ($entity->isNew()) {
            return true;
        }

        return $entity->isAttributeChanged('emailAddressData')
            || $entity->isAttributeChanged('phoneNumberData')
            || $entity->isAttributeChanged('emailAddress')
            || $entity->isAttributeChanged('phoneNumber');
    }

    private function copyIfDifferent(Entity $source, Entity $target): void
    {
        $sourceEmails = $this->readEmailSet($source);
        $sourcePhones = $this->readPhoneSet($source);

        if (
            $this->emailFingerprint($sourceEmails) === $this->emailFingerprint($this->readEmailSet($target))
            && $this->phoneFingerprint($sourcePhones) === $this->phoneFingerprint($this->readPhoneSet($target))
        ) {
            return;
        }

        $target->set('emailAddressData', $sourceEmails);
        $target->set('phoneNumberData', $sourcePhones);

        $this->entityManager->saveEntity($target, [
            self::SKIP_OPTION => true,
        ]);
    }

    /**
     * @return list<stdClass>
     */
    private function readEmailSet(Entity $entity): array
    {
        $rows = $this->normalizeRows($entity->get('emailAddressData'));
        $out = [];

        foreach ($rows as $row) {
            $normalized = $this->normalizeEmailRow($row);

            if ($normalized) {
                $out[] = $normalized;
            }
        }

        if ($out !== []) {
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
        $rows = $this->normalizeRows($entity->get('phoneNumberData'));
        $out = [];

        foreach ($rows as $row) {
            $normalized = $this->normalizePhoneRow($row);

            if ($normalized) {
                $out[] = $normalized;
            }
        }

        if ($out !== []) {
            return $out;
        }

        $primary = trim((string) ($entity->get('phoneNumber') ?? ''));

        if ($primary === '') {
            return [];
        }

        return [$this->phoneRow($primary, true, '')];
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
