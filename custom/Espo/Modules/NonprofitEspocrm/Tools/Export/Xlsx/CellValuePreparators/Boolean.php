<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Export\Xlsx\CellValuePreparators;

use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\Tools\Export\Format\CellValuePreparator;

/**
 * Export bools as CRM-language Yes/No strings (not Excel BOOL).
 *
 * Native BOOL cells follow the Excel UI locale (e.g. Russian Да/Нет on a RU
 * Windows install) instead of the Espo language. Strings use Global.labels.
 */
class Boolean implements CellValuePreparator
{
    public function __construct(
        private Language $language,
    ) {}

    public function prepare(Entity $entity, string $name): ?string
    {
        if (!$entity->has($name)) {
            return null;
        }

        $value = (bool) $entity->get($name);

        return $this->language->translate($value ? 'Yes' : 'No', 'labels', 'Global');
    }
}
