define('nonprofit-espocrm:views/dashlets/meal-count-monthly-stats', [
    'views/dashlets/abstract/base',
    'nonprofit-espocrm:views/reporting/list-stats-footer',
], function (Dep, ListStatsFooter) {

    const PERIOD_ORDER = ListStatsFooter.PERIOD_ORDER || [
        {key: 'year', labelKey: 'reportingListStatsYear'},
        {key: 'month', labelKey: 'reportingListStatsMonth'},
        {key: 'today', labelKey: 'reportingListStatsToday'},
    ];

    return Dep.extend({

        name: 'MealCountMonthlyStats',

        summaryUrl: 'NonprofitEspocrm/reporting/meal-count/summary',
        entityScope: 'MealCount',
        defaultMetrics: ['adults', 'minors', 'totalMeals', 'foodCost'],

        templateContent: `
            <div class="safehouse-reporting-dashlet">
                {{#if loading}}
                    <div class="no-data text-soft">{{loadingLabel}}</div>
                {{else}}
                    {{#if hasStats}}
                        <div class="safehouse-reporting-stats-period-grid">
                            {{#each periodSections}}
                                <div class="safehouse-reporting-stats-period-cell">
                                    <div class="safehouse-reporting-stats-period-title">{{title}}</div>
                                    {{#if range}}
                                        <div class="safehouse-reporting-stats-period-range">{{range}}</div>
                                    {{/if}}
                                    <div class="safehouse-reporting-stats-metrics">
                                        {{#each metrics}}
                                            <div class="safehouse-reporting-stats-metric">
                                                <span class="safehouse-reporting-stats-value safehouse-reporting-stats-value--accent">{{value}}</span>
                                                <span class="safehouse-reporting-stats-label">{{label}}</span>
                                            </div>
                                        {{/each}}
                                    </div>
                                </div>
                            {{/each}}
                        </div>
                    {{else}}
                        <div class="no-data">{{noDataLabel}}</div>
                    {{/if}}
                {{/if}}
            </div>
        `,

        setup() {
            Dep.prototype.setup.call(this);

            this.summary = null;
            this.loading = true;
            this.summaryRequestId = 0;
            this.statsFooter = new ListStatsFooter(this);

            this.loadSummary();
        },

        data() {
            const periodSections = this.buildPeriodSections();

            return {
                loading: this.loading,
                loadingLabel: this.translate('loading', 'labels', 'Global'),
                noDataLabel: this.translate('No Data'),
                hasStats: periodSections.length > 0,
                periodSections: periodSections,
            };
        },

        buildPeriodSections() {
            if (!this.summary) {
                return [];
            }

            const metricList = this.summary.metricList || this.defaultMetrics;

            return PERIOD_ORDER
                .map(period => {
                    const stats = this.summary[period.key];

                    if (!stats) {
                        return null;
                    }

                    return {
                        key: period.key,
                        title: this.translate(period.labelKey, 'labels', 'Global'),
                        range: this.statsFooter.formatPeriodRange(stats.from, stats.to),
                        metrics: metricList.map(key => {
                            const item = this.statsFooter.resolveMetricItem(this.entityScope, key);

                            return {
                                key: key,
                                label: this.translate(key, 'fields', this.entityScope),
                                value: this.statsFooter.formatValue(stats[key], item),
                            };
                        }),
                    };
                })
                .filter(section => section !== null);
        },

        loadSummary() {
            this.loading = true;
            const requestId = Date.now();
            this.summaryRequestId = requestId;

            if (this.isRendered()) {
                this.reRender();
            }

            Espo.Ajax.getRequest(this.summaryUrl)
                .then(data => {
                    if (this.summaryRequestId !== requestId) {
                        return;
                    }

                    this.summary = data;
                    this.loading = false;
                    this.renderStats();
                })
                .catch(() => {
                    if (this.summaryRequestId !== requestId) {
                        return;
                    }

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

        actionRefresh() {
            this.loadSummary();
        },
    });
});
