<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\CaseObj;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Modules\NonprofitEspocrm\Tools\CaseObj\WebsiteReference;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOption;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Mint Case.websiteReferenceId once on create (CRM-owned, never overwritten).
 *
 * Prefix from tipo: sd / sl / rg / sh (default for all other types).
 * Applies to manual create and inbound email Cases.
 */
class AssignWebsiteReferenceId implements BeforeSave
{
    public static int $order = 6;

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        if ($options->get(SaveOption::SKIP_ALL)) {
            return;
        }

        if (!$entity->isNew()) {
            return;
        }

        $existing = trim((string) ($entity->get('websiteReferenceId') ?? ''));

        if ($existing !== '') {
            return;
        }

        $type = (string) ($entity->get('type') ?? '');
        $minted = WebsiteReference::mintForType($type);
        $entity->set('websiteReferenceId', $minted);

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
