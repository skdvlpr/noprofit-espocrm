define('safehouse-crm:views/dashlets/association-meal-count-monthly-stats', [
    'safehouse-crm:views/dashlets/meal-count-monthly-stats',
], function (Dep) {

    return Dep.extend({

        name: 'AssociationMealCountMonthlyStats',

        summaryUrl: 'SafehouseCrm/reporting/association-meal-count/summary',
        entityScope: 'AssociationMealCount',
        defaultMetrics: ['portionCount'],
    });
});
