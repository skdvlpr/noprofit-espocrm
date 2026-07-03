<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\CaseObj;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Config;
use Espo\Entities\InboundEmail;
use Espo\ORM\Entity;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Prepares Segnalazione records created from group InboundEmail intake.
 *
 * Espo core emailToCase() does not set Case.type (required in this module) and does not
 * populate linkParent — our RequireParent hook allows intake cases without parent until
 * the website API links a Lead.
 */
class InboundEmailIntake implements BeforeSave
{
    public static int $order = 4;

    public function __construct(
        private EntityManager $entityManager,
        private Config $config,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $inboundEmailId = $entity->get('inboundEmailId');

        if ($inboundEmailId === null || $inboundEmailId === '') {
            return;
        }

        $this->applyResolvedCaseType($entity, (string) $inboundEmailId);

        if ($entity->isNew()) {
            $this->populateWebsiteIntakeFields($entity);
            $this->linkParentFromEmailRelations($entity);

            return;
        }

        if ($entity->get('type') === 'Other' || $entity->isAttributeChanged('description')) {
            $this->populateWebsiteIntakeFields($entity);
        }
    }

    private function applyResolvedCaseType(Entity $entity, string $inboundEmailId): void
    {
        $description = (string) $entity->get('description');

        $resolvedType = $this->normalizeCaseType($this->extractCaseTypeFromDescription($description))
            ?? $this->normalizeCaseType($this->extractSportelloDisplayName($description))
            ?? $this->resolveCaseType($inboundEmailId);

        $currentType = (string) ($entity->get('type') ?? '');

        if ($resolvedType !== null && ($currentType === '' || $currentType === 'Other')) {
            $entity->set('type', $resolvedType);

            return;
        }

        if ($entity->isNew() && $currentType === '') {
            $entity->set('type', 'Other');
        }
    }

    private function linkParentFromEmailRelations(Entity $entity): void
    {
        if ($entity->get('parentId') !== null && $entity->get('parentId') !== '') {
            return;
        }

        if ($entity->get('leadId')) {
            $entity->set([
                'parentType' => 'Lead',
                'parentId' => $entity->get('leadId'),
            ]);

            return;
        }

        if ($entity->get('contactId')) {
            $entity->set([
                'parentType' => 'Contact',
                'parentId' => $entity->get('contactId'),
            ]);

            return;
        }

        if ($entity->get('accountId')) {
            $entity->set([
                'parentType' => 'Account',
                'parentId' => $entity->get('accountId'),
            ]);
        }
    }

    private function populateWebsiteIntakeFields(Entity $entity): void
    {
        $referenceId = $this->extractWebsiteReferenceId(
            (string) $entity->get('name'),
            (string) $entity->get('description'),
        );

        if ($referenceId !== null) {
            $entity->set('websiteReferenceId', $referenceId);
        }

        if (!$entity->get('websiteContactName')) {
            $contactName = $this->extractWebsiteContactName((string) $entity->get('description'));

            if ($contactName !== null) {
                $entity->set('websiteContactName', $contactName);
            }
        }

        if (!$entity->get('sportelloDisplayName')) {
            $sportello = $this->extractSportelloDisplayName((string) $entity->get('description'))
                ?? $this->resolveSportelloDisplayName((string) $entity->get('type'));

            if ($sportello !== null) {
                $entity->set('sportelloDisplayName', $sportello);
            }
        }
    }

    private function extractWebsiteReferenceId(string ...$sources): ?string
    {
        foreach ($sources as $source) {
            if ($source === '') {
                continue;
            }

            if (preg_match('/\[(SH-[a-z0-9-]+)\]/i', $source, $matches) === 1) {
                return strtolower($matches[1]);
            }
        }

        return null;
    }

    private function extractWebsiteContactName(string $description): ?string
    {
        if ($description === '') {
            return null;
        }

        if (preg_match('/^Nome:\s*(.+)$/mi', $description, $matches) !== 1) {
            return null;
        }

        $name = trim($matches[1]);

        return $name !== '' ? $name : null;
    }

    private function extractSportelloDisplayName(string $description): ?string
    {
        if ($description === '') {
            return null;
        }

        if (preg_match('/^Sportello:\s*(.+)$/mi', $description, $matches) !== 1) {
            return null;
        }

        $label = trim($matches[1]);

        return $label !== '' ? $label : null;
    }

    private function extractCaseTypeFromDescription(string $description): ?string
    {
        if ($description === '') {
            return null;
        }

        if (preg_match('/^Tipo segnalazione:\s*(.+)$/mi', $description, $matches) !== 1) {
            return null;
        }

        $caseType = trim($matches[1]);

        return $caseType !== '' ? $caseType : null;
    }

    private function normalizeCaseType(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $knownTypes = [
            'BeneficiaryRequest',
            'GuestIntake',
            'ServiceAccess',
            'InformationRequest',
            'Complaint',
            'PartnerOrganization',
            'SponsorOrDonor',
            'SupplierOrVendor',
            'InstitutionalBody',
            'InternalReferral',
            'VolunteerMatter',
            'MemberMatter',
            'LegalOrAdministrative',
            'SportelloDigitale',
            'SportelloLegale',
            'Other',
        ];

        if (in_array($value, $knownTypes, true)) {
            return $value;
        }

        $normalized = strtolower(preg_replace('/\s+/', '', $value) ?? $value);

        $aliases = [
            'sportellodigitale' => 'SportelloDigitale',
            'sportellolegale' => 'SportelloLegale',
            'altro' => 'Other',
            'richiestadiassistenza' => 'BeneficiaryRequest',
            'richiestainformazioni' => 'InformationRequest',
        ];

        return $aliases[$normalized] ?? null;
    }

    private function resolveSportelloDisplayName(string $caseType): ?string
    {
        return match ($caseType) {
            'SportelloDigitale' => 'Sportello digitale',
            'SportelloLegale' => 'Sportello legale',
            default => null,
        };
    }

    private function resolveCaseType(string $inboundEmailId): ?string
    {
        /** @var ?InboundEmail $inboundEmail */
        $inboundEmail = $this->entityManager
            ->getEntityById(InboundEmail::ENTITY_TYPE, $inboundEmailId);

        if ($inboundEmail === null) {
            return null;
        }

        $customType = $inboundEmail->get('caseTypeDefault');

        if (is_string($customType) && $customType !== '') {
            return $customType;
        }

        $emailAddress = strtolower(trim((string) $inboundEmail->get('emailAddress')));

        if ($emailAddress === '') {
            return null;
        }

        /** @var array<string, string> $map */
        $map = $this->config->get('inboundEmailCaseTypes') ?? [];

        return $map[$emailAddress] ?? null;
    }
}
