<?php

namespace Espo\Modules\NonprofitEspocrm\Tools;

use Espo\Core\Exceptions\BadRequest;
use Espo\Entities\EmailAddress as EmailAddressEntity;
use Espo\Entities\PhoneNumber as PhoneNumberEntity;
use Espo\Entities\User;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;
use Espo\Repositories\EmailAddress as EmailAddressRepository;
use Espo\Repositories\PhoneNumber as PhoneNumberRepository;
use stdClass;

/**
 * Legacy email/phone uniqueness helper for person records.
 *
 * VolunteerEmployee / Member entities are retired (Contact STI). Contact
 * profile sync lives in {@see UserContactProfileSync}. This class keeps an
 * empty person-type list so callers no longer treat retired entities as
 * person scopes; uniqueness checks are effectively a no-op.
 *
 * When {@see SaveOption::SKIP_ALL} is set, this class does nothing (same as other
 * hooks) so imports/migrations can bypass checks intentionally.
 */
class PersonContactSync
{
    /**
     * Retired VE/Member removed. Contact uniqueness is handled elsewhere.
     *
     * @var string[]
     */
    public const PERSON_ENTITY_TYPES = [];

    public function __construct(
        private EntityManager $entityManager,
    ) {}

    private function emailRepository(): EmailAddressRepository
    {
        /** @var EmailAddressRepository */
        return $this->entityManager->getRepository(EmailAddressEntity::ENTITY_TYPE);
    }

    private function phoneRepository(): PhoneNumberRepository
    {
        /** @var PhoneNumberRepository */
        return $this->entityManager->getRepository(PhoneNumberEntity::ENTITY_TYPE);
    }

    public function process(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (self::PERSON_ENTITY_TYPES === []) {
            return;
        }

        if (!in_array($entity->getEntityType(), self::PERSON_ENTITY_TYPES, true)) {
            return;
        }

        $assignedUserId = $entity->get('assignedUserId');

        if ($assignedUserId) {
            $this->enforceAssignedUserPrimary($entity, $assignedUserId);
        }

        $this->assertUniqueEmails($entity);
        $this->assertUniquePhones($entity);
    }

    /**
     * Syncs email/phone from the assigned user:
     * - If assigned user has no email: skip email enforcement entirely.
     * - If entity has no email: copy user's email as primary.
     * - If entity has a different email: user's email becomes primary,
     *   entity's original email becomes secondary.
     * - If entity has the same email: keep as-is.
     *
     * Same logic for phone numbers (silently skip if user has no phone).
     */
    private function enforceAssignedUserPrimary(Entity $entity, string $assignedUserId): void
    {
        /** @var ?User $assignedUser */
        $assignedUser = $this->entityManager->getEntityById(User::ENTITY_TYPE, $assignedUserId);

        if (!$assignedUser) {
            throw new BadRequest('Assigned user not found.');
        }

        $primaryEmail = $this->primaryEmailFromUser($assignedUser);

        if ($primaryEmail !== '') {
            $mergedEmails = $this->mergeEmailRows(
                $this->collectEmailRows($entity),
                $primaryEmail
            );
            $entity->set('emailAddressData', $mergedEmails);
        }

        $primaryPhone = $this->primaryPhoneFromUser($assignedUser);

        $mergedPhones = $this->mergePhoneRows(
            $this->collectPhoneRows($entity),
            $primaryPhone
        );
        $entity->set('phoneNumberData', $mergedPhones);
    }

    private function primaryEmailFromUser(User $user): string
    {
        $rows = $this->emailRepository()->getEmailAddressData($user);
        foreach ($rows as $row) {
            if (!empty($row->primary) && !empty($row->emailAddress)) {
                return trim((string) $row->emailAddress);
            }
        }

        $fallback = $user->get('emailAddress');
        if (is_string($fallback) && $fallback !== '') {
            return trim($fallback);
        }

        return '';
    }

    private function primaryPhoneFromUser(User $user): string
    {
        $rows = $this->phoneRepository()->getPhoneNumberData($user);
        foreach ($rows as $row) {
            if (!empty($row->primary) && !empty($row->phoneNumber)) {
                return trim((string) $row->phoneNumber);
            }
        }

        $fallback = $user->get('phoneNumber');
        if (is_string($fallback) && $fallback !== '') {
            return trim($fallback);
        }

        return '';
    }

    /**
     * @return stdClass[]
     */
    private function collectEmailRows(Entity $entity): array
    {
        $raw = $entity->get('emailAddressData');
        if (is_array($raw) && $raw !== []) {
            return array_map(fn ($row) => $this->normalizeEmailRow($row), $raw);
        }

        $rows = $entity->hasId()
            ? $this->emailRepository()->getEmailAddressData($entity)
            : [];

        $singleton = $entity->get('emailAddress');
        if (is_string($singleton) && trim($singleton) !== '') {
            $singletonLower = strtolower(trim($singleton));
            foreach ($rows as $row) {
                if (strtolower(trim((string) ($row->emailAddress ?? ''))) === $singletonLower) {
                    return $rows;
                }
            }
            $rows[] = (object) [
                'emailAddress' => $singleton,
                'primary'      => true,
                'optOut'       => false,
                'invalid'      => false,
            ];
        }

        return $rows;
    }

    private function normalizeEmailRow(mixed $row): stdClass
    {
        if ($row instanceof stdClass) {
            return $row;
        }
        if (is_array($row)) {
            return (object) $row;
        }

        return (object) [];
    }

    /**
     * @param stdClass[] $rows
     * @return stdClass[]
     */
    private function mergeEmailRows(array $rows, string $primaryDisplay): array
    {
        $primaryLower = strtolower(trim($primaryDisplay));
        $extras = [];
        foreach ($rows as $row) {
            $addr = strtolower(trim((string) ($row->emailAddress ?? '')));
            if ($addr === '' || $addr === $primaryLower) {
                continue;
            }
            $copy = (object) [
                'emailAddress' => $row->emailAddress,
                'primary'      => false,
                'optOut'       => (bool) ($row->optOut ?? false),
                'invalid'      => (bool) ($row->invalid ?? false),
            ];
            $extras[] = $copy;
        }

        $primaryRow = (object) [
            'emailAddress' => $primaryDisplay,
            'primary'      => true,
            'optOut'       => false,
            'invalid'      => false,
        ];

        return array_merge([$primaryRow], $extras);
    }

    /**
     * @return stdClass[]
     */
    private function collectPhoneRows(Entity $entity): array
    {
        $raw = $entity->get('phoneNumberData');
        if (is_array($raw) && $raw !== []) {
            return array_map(fn ($row) => $this->normalizePhoneRow($row), $raw);
        }

        $rows = $entity->hasId()
            ? $this->phoneRepository()->getPhoneNumberData($entity)
            : [];

        $singleton = $entity->get('phoneNumber');
        if (is_string($singleton) && trim($singleton) !== '') {
            $singletonKey = $this->normalizePhoneKey($singleton);
            foreach ($rows as $row) {
                if ($this->normalizePhoneKey((string) ($row->phoneNumber ?? '')) === $singletonKey) {
                    return $rows;
                }
            }
            $rows[] = (object) [
                'phoneNumber' => $singleton,
                'type'        => 'Mobile',
                'primary'     => true,
                'optOut'       => false,
                'invalid'      => false,
            ];
        }

        return $rows;
    }

    private function normalizePhoneRow(mixed $row): stdClass
    {
        if ($row instanceof stdClass) {
            return $row;
        }
        if (is_array($row)) {
            return (object) $row;
        }

        return (object) [];
    }

    /**
     * @param stdClass[] $rows
     * @return stdClass[]
     */
    private function mergePhoneRows(array $rows, string $primaryPhone): array
    {
        if ($primaryPhone === '') {
            $filtered = [];
            foreach ($rows as $row) {
                $num = trim((string) ($row->phoneNumber ?? ''));
                if ($num === '') {
                    continue;
                }
                $copy = (object) [
                    'phoneNumber' => $row->phoneNumber,
                    'type'        => $row->type ?? 'Mobile',
                    'primary'     => false,
                    'optOut'      => (bool) ($row->optOut ?? false),
                    'invalid'     => (bool) ($row->invalid ?? false),
                ];
                $filtered[] = $copy;
            }

            return $this->ensureSinglePhonePrimary($filtered);
        }

        $primaryNorm = $this->normalizePhoneKey($primaryPhone);
        $extras = [];
        foreach ($rows as $row) {
            $num = trim((string) ($row->phoneNumber ?? ''));
            if ($num === '' || $this->normalizePhoneKey($num) === $primaryNorm) {
                continue;
            }
            $extras[] = (object) [
                'phoneNumber' => $row->phoneNumber,
                'type'        => $row->type ?? 'Mobile',
                'primary'     => false,
                'optOut'      => (bool) ($row->optOut ?? false),
                'invalid'     => (bool) ($row->invalid ?? false),
            ];
        }

        $primaryRow = (object) [
            'phoneNumber' => $primaryPhone,
            'type'        => 'Mobile',
            'primary'     => true,
            'optOut'      => false,
            'invalid'     => false,
        ];

        return $this->ensureSinglePhonePrimary(array_merge([$primaryRow], $extras));
    }

    /**
     * @param stdClass[] $rows
     * @return stdClass[]
     */
    private function ensureSinglePhonePrimary(array $rows): array
    {
        $found = false;
        foreach ($rows as $row) {
            if (!empty($row->primary)) {
                if ($found) {
                    $row->primary = false;
                } else {
                    $found = true;
                }
            }
        }
        if (!$found && $rows !== []) {
            $rows[0]->primary = true;
        }

        return $rows;
    }

    private function assertUniqueEmails(Entity $entity): void
    {
        foreach ($this->collectEmailRows($entity) as $row) {
            $address = strtolower(trim((string) ($row->emailAddress ?? '')));
            if ($address === '') {
                continue;
            }

            $ea = $this->emailRepository()->getByAddress($address);
            if (!$ea) {
                continue;
            }

            $exception = $entity->hasId() ? $entity : null;

            $conflicts = $this->emailRepository()->getEntityListByAddressId(
                $ea->getId(),
                $exception,
                null,
                true
            );

            foreach ($conflicts as $foreign) {
                if (!in_array($foreign->getEntityType(), self::PERSON_ENTITY_TYPES, true)) {
                    continue;
                }
                if ($foreign->getId() === $entity->getId() && $foreign->getEntityType() === $entity->getEntityType()) {
                    continue;
                }

                throw new BadRequest(sprintf(
                    'Email address `%s` is already used on %s record `%s`.',
                    $address,
                    $foreign->getEntityType(),
                    $foreign->get('name') ?? $foreign->getId()
                ));
            }
        }
    }

    private function assertUniquePhones(Entity $entity): void
    {
        foreach ($this->collectPhoneRows($entity) as $row) {
            $number = trim((string) ($row->phoneNumber ?? ''));
            if ($number === '') {
                continue;
            }

            $pn = $this->phoneRepository()->getByNumber($number);
            if (!$pn) {
                continue;
            }

            $exception = $entity->hasId() ? $entity : null;

            $conflicts = $this->phoneRepository()->getEntityListByPhoneNumberId(
                $pn->getId(),
                $exception
            );

            foreach ($conflicts as $foreign) {
                if (!in_array($foreign->getEntityType(), self::PERSON_ENTITY_TYPES, true)) {
                    continue;
                }
                if ($foreign->getId() === $entity->getId() && $foreign->getEntityType() === $entity->getEntityType()) {
                    continue;
                }

                throw new BadRequest(sprintf(
                    'Phone number `%s` is already used on %s record `%s`.',
                    $number,
                    $foreign->getEntityType(),
                    $foreign->get('name') ?? $foreign->getId()
                ));
            }
        }
    }

    private function normalizePhoneKey(string $number): string
    {
        return preg_replace('/\s+/', '', $number) ?? $number;
    }
}
