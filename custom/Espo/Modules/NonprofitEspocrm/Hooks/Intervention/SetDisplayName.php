<?php

namespace Espo\Modules\NonprofitEspocrm\Hooks\Intervention;

use Espo\Core\Hook\Hook\BeforeSave;
use Espo\Core\Utils\Language;
use Espo\ORM\Entity;
use Espo\ORM\Repository\Option\SaveOptions;

/**
 * Builds the read-only name from translated department label + date.
 */
class SetDisplayName implements BeforeSave
{
    public static int $order = 20;

    public function __construct(
        private Language $language,
    ) {}

    public function beforeSave(Entity $entity, SaveOptions $options): void
    {
        $department = $entity->get('department');
        $date = $entity->get('interventionDate');

        if ($department === null || $department === '' || $date === null || $date === '') {
            return;
        }

        $label = $this->language->translateOption(
            (string) $department,
            'department',
            'Intervention'
        );

        $entity->set('name', $label . ' — ' . $date);
    }
}
