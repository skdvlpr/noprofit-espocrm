<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

use Espo\Core\Utils\Metadata;

class CapableEntityTypeResolver
{
    public function __construct(
        private DateSourceEntityTypesReader $dateSourceEntityTypesReader,
        private Metadata $metadata
    ) {}

    /**
     * @return list<string>
     */
    public function getProvisionableEntityTypes(): array
    {
        $list = [];

        foreach ($this->dateSourceEntityTypesReader->readActiveTargetEntityTypes() as $entityType) {
            if ($this->metadata->get(['scopes', $entityType, 'entity']) !== true) {
                continue;
            }

            $list[] = $entityType;
        }

        return $list;
    }
}
