<?php

namespace Espo\Modules\NonprofitEspocrm\Tools\Reporting;

class ReportingProfileRegistry
{
    private const MEAL_COUNT_SUM = ['adults', 'minors', 'totalMeals', 'foodCost'];

    private const ASSOCIATION_MEAL_COUNT_SUM = ['portionCount'];

    public function getProfile(string $entityType): ?ReportingEntityProfile
    {
        if ($entityType === 'MealCount') {
            return new ReportingEntityProfile(
                'MealCount',
                'date',
                self::MEAL_COUNT_SUM,
            );
        }

        if ($entityType === 'AssociationMealCount') {
            return new ReportingEntityProfile(
                'AssociationMealCount',
                'date',
                self::ASSOCIATION_MEAL_COUNT_SUM,
            );
        }

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
