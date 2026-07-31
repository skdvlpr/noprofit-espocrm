<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\CaseObj;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\CaseObj\WebsiteReference;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Ensure Case.websiteReferenceId exists (CRM-owned).
 *
 * - Mint only when empty (create OR re-save of legacy rows without ID).
 * - Never overwrite a non-empty stored ID (restore fetched value if changed).
 * Prefix from tipo: sd / sl / rg / sh (default for all other types).
 */
class AssignWebsiteReferenceId implements BeforeSave
{
    public static int $order = 6;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        // Protect existing IDs: never replace / clear a previously stored value.
        if (!$entity->isNew() && $entity->hasFetched('websiteReferenceId')) {
            $fetched = trim((string) ($entity->getFetched('websiteReferenceId') ?? ''));

            if ($fetched !== '') {
                if ((string) ($entity->get('websiteReferenceId') ?? '') !== $fetched) {
                    $entity->set('websiteReferenceId', $fetched);
                }

                return;
            }
        }

        $existing = trim((string) ($entity->get('websiteReferenceId') ?? ''));

        if ($existing !== '') {
            return;
        }

        $type = (string) ($entity->get('type') ?? '');
        $entity->set('websiteReferenceId', WebsiteReference::mintForType($type));

        if (!$entity->get('sportelloDisplayName')) {
            $label = match ($type) {
                'SportelloDigitale' => 'Sportello digitale',
                'SportelloLegale' => 'Sportello legale',
                'RichiestaGenerica' => 'Richiesta generica',
                default => null,
            };

            if ($label !== null) {
                $entity->set('sportelloDisplayName', $label);
            }
        }
    }
}
