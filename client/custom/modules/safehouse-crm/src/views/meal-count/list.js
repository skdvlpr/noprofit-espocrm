define('safehouse-crm:views/meal-count/list', [
    'safehouse-crm:views/record/list-inline-edit',
    'safehouse-crm:views/reporting/list-stats-footer',
], function (Dep, ListStatsFooter) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.apply(this, arguments);

            this.statsFooter = new ListStatsFooter(this);
            this.reportingStats = null;

            this.loadReportingStats();
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.renderReportingStats();
        },

        loadReportingStats() {
            Espo.Ajax.getRequest('SafehouseCrm/reporting/meal-count/summary')
                .then(data => {
                    this.reportingStats = data;

                    if (this.isRendered()) {
                        this.renderReportingStats();
                    }
                })
                .catch(() => {
                    this.reportingStats = null;
                });
        },

        renderReportingStats() {
            if (!this.reportingStats) {
                return;
            }

            const $container = this.getReportingStatsContainer();

            if (!$container.length) {
                return;
            }

            const items = [
                {key: 'adults', label: this.translate('adults', 'fields', 'MealCount')},
                {key: 'minors', label: this.translate('minors', 'fields', 'MealCount')},
                {key: 'totalMeals', label: this.translate('totalMeals', 'fields', 'MealCount')},
                {key: 'foodCost', label: this.translate('foodCost', 'fields', 'MealCount')},
            ];

            const month = this.reportingStats.month || {};
            const year = this.reportingStats.year || {};

            const monthTitle = this.translate('reportingListStatsMonth', 'labels', 'Global')
                + (month.from && month.to ? ` (${month.from} – ${month.to})` : '');
            const yearTitle = this.translate('reportingListStatsYear', 'labels', 'Global')
                + (year.from && year.to ? ` (${year.from} – ${year.to})` : '');

            this.statsFooter.render($container, month, {
                title: monthTitle,
                items,
                removeSelector: '.safehouse-reporting-stats-month',
                extraClass: 'safehouse-reporting-stats-month',
            });

            this.statsFooter.render($container, year, {
                title: yearTitle,
                items,
                removeSelector: '.safehouse-reporting-stats-year',
                extraClass: 'safehouse-reporting-stats-year',
            });
        },

        getReportingStatsContainer() {
            const $listContainer = this.$el.find('.list-container');

            if ($listContainer.length) {
                return $listContainer;
            }

            return this.$el.find('.content');
        },
    });
});
