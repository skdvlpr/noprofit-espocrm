<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\Rebuild\RebuildAction;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\ContactAdmissionCopy;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;

/**
 * Fill empty Contact board fields and admission file from the converted Lead.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
 *
 * @noinspection PhpUnused
 */
class BackfillContactAdmissionFromLead implements RebuildAction
{
    public function __construct(
        private EntityManager $entityManager
    ) {}

    public function process(): void
    {
        $leads = $this->entityManager
            ->getRDBRepository('Lead')
            ->where([
                'status' => 'Converted',
                'createdContactId!=' => null,
            ])
            ->find();

        foreach ($leads as $lead) {
            $contactId = $lead->get('createdContactId');

            if (!is_string($contactId) || $contactId === '') {
                continue;
            }

            $contact = $this->entityManager->getEntityById('Contact', $contactId);

            if ($contact === null) {
                continue;
            }

            if (!ContactAdmissionCopy::apply($lead, $contact)) {
                continue;
            }

            $this->entityManager->saveEntity($contact, [
                SaveOption::SKIP_ALL => true,
            ]);
        }
    }
}
