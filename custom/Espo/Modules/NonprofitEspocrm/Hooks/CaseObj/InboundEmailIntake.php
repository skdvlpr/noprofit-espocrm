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
        if (!$entity->isNew()) {
            return;
        }

        $inboundEmailId = $entity->get('inboundEmailId');

        if ($inboundEmailId === null || $inboundEmailId === '') {
            return;
        }

        if ($entity->get('type') === null || $entity->get('type') === '') {
            $entity->set('type', $this->resolveCaseType((string) $inboundEmailId) ?? 'Other');
        }

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
