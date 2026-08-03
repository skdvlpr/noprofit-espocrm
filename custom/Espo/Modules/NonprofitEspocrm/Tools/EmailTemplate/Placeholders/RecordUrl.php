<?php

declare(strict_types=1);

namespace Espo\Modules\NonprofitEspocrm\Tools\EmailTemplate\Placeholders;

use Espo\Modules\NonprofitEspocrm\Tools\EmailTemplate\TemplatePlaceholderHelper;
use Espo\ORM\EntityManager;
use Espo\Tools\EmailTemplate\Data;
use Espo\Tools\EmailTemplate\Placeholder;

/**
 * Native EmailTemplate placeholder `{recordUrl}` — deep link to the parent
 * (or related) record. Empty string when no record is in context.
 *
 * @noinspection PhpUnused
 */
class RecordUrl implements Placeholder
{
    public function __construct(
        private TemplatePlaceholderHelper $helper,
        private EntityManager $entityManager,
    ) {}

    public function get(Data $data): string
    {
        $parent = $data->getParent();

        if ($parent) {
            return $this->helper->urlFor($parent);
        }

        $parentType = $data->getParentType();
        $parentId = $data->getParentId();

        if (is_string($parentType) && $parentType !== '' && is_string($parentId) && $parentId !== '') {
            return $this->helper->urlFor(
                $this->entityManager->getEntityById($parentType, $parentId)
            );
        }

        $relatedType = $data->getRelatedType();
        $relatedId = $data->getRelatedId();

        if (is_string($relatedType) && $relatedType !== '' && is_string($relatedId) && $relatedId !== '') {
            return $this->helper->urlFor(
                $this->entityManager->getEntityById($relatedType, $relatedId)
            );
        }

        return '';
    }
}
