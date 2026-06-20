<?php

namespace Espo\Modules\SafehouseCrm\Tools\Reporting;

class ReportingProfileRegistry
{
    private const MEAL_COUNT_SUM = ['adults', 'minors', 'totalMeals', 'foodCost'];

    public function getProfile(string $entityType): ?ReportingEntityProfile
    {
        if ($entityType === 'MealCount') {
            return new ReportingEntityProfile(
                'MealCount',
                'date',
                self::MEAL_COUNT_SUM,
            );
        }

        // AssociationMealCount — same shape; profile added in Task 7.4.
        return null;
    }

    public function isReportingEntity(string $entityType): bool
    {
        return $this->getProfile($entityType) !== null;
    }

    /**
     * @return string[]
     */
    public function getReportingEntityTypes(): array
    {
        $types = [];

        foreach (['MealCount', 'AssociationMealCount'] as $entityType) {
            if ($this->getProfile($entityType) !== null) {
                $types[] = $entityType;
            }
        }

        return $types;
    }
}
