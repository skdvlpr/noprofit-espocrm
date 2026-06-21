define('safehouse-crm:views/dashlets/meal-count-monthly-stats', [
    'views/dashlets/abstract/base',
], function (Dep) {

    return Dep.extend({

        name: 'MealCountMonthlyStats',

        summaryUrl: 'SafehouseCrm/reporting/meal-count/summary',
        entityScope: 'MealCount',
        defaultMetrics: ['adults', 'minors', 'totalMeals', 'foodCost'],

        templateContent: `
            <div class="safehouse-mealcount-dashlet">
                <div class="btn-group btn-group-xs-wide margin-bottom" role="group">
                    <button type="button"
                        class="btn btn-text {{#if periodIsMonth}}active{{/if}}"
                        data-action="setPeriod"
                        data-period="month">{{monthLabel}}</button>
                    <button type="button"
                        class="btn btn-text {{#if periodIsYear}}active{{/if}}"
                        data-action="setPeriod"
                        data-period="year">{{yearLabel}}</button>
                </div>
                {{#if loading}}
                    <div class="text-muted">{{translate 'loading'}}</div>
                {{else}}
                    {{#if hasStats}}
                        <div class="text-soft small margin-bottom">{{periodRange}}</div>
                        <div class="safehouse-reporting-stats-grid">
                            {{#each metricItems}}
                                <div class="safehouse-reporting-stats-item">
                                    <span class="safehouse-reporting-stats-label">{{label}}: </span>
                                    <span class="safehouse-reporting-stats-value">{{value}}</span>
                                </div>
                            {{/each}}
                        </div>
                    {{else}}
                        <div class="text-muted">{{translate 'No Data'}}</div>
                    {{/if}}
                {{/if}}
            </div>
        `,

        setup() {
            Dep.prototype.setup.call(this);

            this.period = this.optionsData.period || 'month';
            this.summary = null;
            this.loading = true;

            this.addActionHandler('setPeriod', data => {
                const period = data.period;

                if (!period || period === this.period) {
                    return;
                }

                this.period = period;
                this.optionsData.period = period;
                this.getPreferences().setDashletOptions(this.id, this.optionsData);
                this.getPreferences().save();
                this.renderStats();
            });
        },

        afterRender() {
            this.loadSummary();
        },

        data() {
            const stats = this.getPeriodStats();
            const metricList = (this.summary && this.summary.metricList) ||
                this.defaultMetrics;

            const metricItems = metricList.map(key => ({
                key: key,
                label: this.translate(key, 'fields', this.entityScope),
                value: this.formatValue(stats ? stats[key] : null),
            }));

            return {
                loading: this.loading,
                hasStats: !!stats && metricItems.length > 0,
                periodIsMonth: this.period === 'month',
                periodIsYear: this.period === 'year',
                monthLabel: this.translate('reportingListStatsMonth', 'labels', 'Global'),
                yearLabel: this.translate('reportingListStatsYear', 'labels', 'Global'),
                periodRange: stats && stats.from && stats.to ? `${stats.from} – ${stats.to}` : '',
                metricItems: metricItems,
            };
        },

        loadSummary() {
            this.loading = true;
            this.reRender();

            Espo.Ajax.getRequest(this.summaryUrl)
                .then(data => {
                    this.summary = data;
                    this.loading = false;
                    this.renderStats();
                })
                .catch(() => {
                    this.summary = null;
                    this.loading = false;
                    this.renderStats();
                });
        },

        renderStats() {
            if (this.isRendered()) {
                this.reRender();
            }
        },

        getPeriodStats() {
            if (!this.summary) {
                return null;
            }

            return this.period === 'year' ? this.summary.year : this.summary.month;
        },

        formatValue(value) {
            if (value === null || value === undefined) {
                return '0';
            }

            if (typeof value === 'number') {
                return Number.isInteger(value) ? String(value) : value.toFixed(2);
            }

            return String(value);
        },

        actionRefresh() {
            this.loadSummary();
        },
    });
});
