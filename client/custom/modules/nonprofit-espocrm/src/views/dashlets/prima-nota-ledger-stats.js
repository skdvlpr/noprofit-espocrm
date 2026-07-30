define('nonprofit-espocrm:views/dashlets/prima-nota-ledger-stats', [
    'views/dashlets/abstract/base',
    'nonprofit-espocrm:views/reporting/list-stats-footer',
], function (Dep, ListStatsFooter) {

    const PERIOD_ORDER = ListStatsFooter.PERIOD_ORDER || [
        {key: 'year', labelKey: 'reportingListStatsYear'},
        {key: 'month', labelKey: 'reportingListStatsMonth'},
        {key: 'today', labelKey: 'reportingListStatsToday'},
    ];

    return Dep.extend({

        name: 'PrimaNotaLedgerStats',

        summaryUrl: 'NonprofitEspocrm/reporting/prima-nota/summary',
        entityScope: 'PrimaNota',
        defaultMetrics: ['amountIn', 'amountOut', 'managementBalance'],

        templateContent: `
            <div class="safehouse-reporting-dashlet">
                {{#if loading}}
                    <div class="no-data text-soft">{{loadingLabel}}</div>
                {{else}}
                    {{#if hasStats}}
                        {{#if cashBalance}}
                            <div class="safehouse-reporting-stats-cash">
                                <div class="safehouse-reporting-stats-period-title">{{cashBalance.title}}</div>
                                {{#if cashBalance.range}}
                                    <div class="safehouse-reporting-stats-period-range">{{cashBalance.range}}</div>
                                {{/if}}
                                <div class="safehouse-reporting-stats-metrics">
                                    <div class="safehouse-reporting-stats-metric">
                                        <span class="safehouse-reporting-stats-value safehouse-reporting-stats-value--accent">{{cashBalance.value}}</span>
                                        <span class="safehouse-reporting-stats-label">{{cashBalance.label}}</span>
                                    </div>
                                </div>
                            </div>
                        {{/if}}
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
            return {
                loading: this.loading,
                loadingLabel: this.translate('loading', 'labels', 'Global'),
                noDataLabel: this.translate('No Data'),
                hasStats: this.buildPeriodSections().length > 0 || !!this.buildCashBalanceSection(),
                cashBalance: this.buildCashBalanceSection(),
                periodSections: this.buildPeriodSections(),
            };
        },

        buildCashBalanceSection() {
            if (!this.summary || !this.summary.cashBalance) {
                return null;
            }

            const cash = this.summary.cashBalance;
            const item = {
                ...this.statsFooter.resolveMetricItem(this.entityScope, 'amountIn'),
                fieldType: 'currency',
                currency: 'EUR',
            };

            let range = cash.asOf || '';
            if (cash.openingAsOf) {
                range = cash.openingAsOf + ' → ' + (cash.asOf || '');
            }

            return {
                title: this.translate('cashBalance', 'fields', this.entityScope),
                label: this.translate('cashBalance', 'fields', this.entityScope),
                range: range,
                value: this.statsFooter.formatValue(cash.balance, item),
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
                            const formatFieldMap = {
                                managementBalance: 'amountIn',
                                plannedBalance: 'amountIn',
                                plannedAmountIn: 'amountIn',
                                plannedAmountOut: 'amountOut',
                            };
                            const formatField = formatFieldMap[key] || key;
                            const item = {
                                ...this.statsFooter.resolveMetricItem(this.entityScope, formatField),
                                fieldType: 'currency',
                                currency: 'EUR',
                            };

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
