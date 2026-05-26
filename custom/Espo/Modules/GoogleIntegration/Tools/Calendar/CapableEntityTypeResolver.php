<?php

namespace Espo\Modules\GoogleIntegration\Tools\Calendar;

class CapableEntityTypeResolver
{
    public function __construct(
        private DateSourceEntityTypesReader $dateSourceEntityTypesReader
    ) {}

    /**
     * @return list<string>
     */
    public function getProvisionableEntityTypes(): array
    {
        return $this->dateSourceEntityTypesReader->readActiveTargetEntityTypes();
    }
}
