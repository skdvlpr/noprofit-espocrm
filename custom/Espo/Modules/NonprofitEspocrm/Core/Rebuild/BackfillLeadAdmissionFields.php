<?php

namespace Espo\Modules\NonprofitEspocrm\Core\Rebuild;

use Espo\Core\Rebuild\RebuildAction;
use Espo\Core\Utils\Log;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\AdmissionPdf;
use Espo\Modules\NonprofitEspocrm\Tools\Admission\NewsletterConsent;
use Espo\ORM\EntityManager;
use Espo\ORM\Repository\Option\SaveOption;
use Throwable;

/**
 * After schema rebuild, copy site newsletter lines into Lead.newsletterConsent
 * and seed the admission PDF template. New columns come from entityDefs
 * during rebuildDatabase, which runs before rebuild actions.
 *
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/commands.md
 * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/hooks.md
 *
 * @noinspection PhpUnused
 */
class BackfillLeadAdmissionFields implements RebuildAction
{
    public function __construct(
        private EntityManager $entityManager,
        private AdmissionPdf $admissionPdf,
        private Log $log,
    ) {}

    public function process(): void
    {
        try {
            $this->admissionPdf->provisionTemplate();
        } catch (Throwable $e) {
            $this->log->warning('Admission PDF template was not seeded: ' . $e->getMessage());
        }

        $leads = $this->entityManager
            ->getRDBRepository('Lead')
            ->where([
                'OR' => [
                    ['description*' => '%Newsletter: acconsente%'],
                    ['description*' => '%Newsletter: non acconsente%'],
                ],
            ])
            ->find();

        foreach ($leads as $lead) {
            NewsletterConsent::sync($lead);

            if (
                !$lead->isAttributeChanged('newsletterConsent')
                && !$lead->isAttributeChanged('description')
            ) {
                continue;
            }

            $this->entityManager->saveEntity($lead, [
                SaveOption::SKIP_ALL => true,
            ]);
        }
    }
}
