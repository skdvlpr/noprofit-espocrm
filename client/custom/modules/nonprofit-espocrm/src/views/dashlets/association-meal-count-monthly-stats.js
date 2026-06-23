define('nonprofit-espocrm:views/dashlets/association-meal-count-monthly-stats', [
    'nonprofit-espocrm:views/dashlets/meal-count-monthly-stats',
], function (Dep) {

    return Dep.extend({

        name: 'AssociationMealCountMonthlyStats',

        summaryUrl: 'NonprofitEspocrm/reporting/association-meal-count/summary',
        entityScope: 'AssociationMealCount',
        defaultMetrics: ['portionCount'],
    });
});
