define('nonprofit-espocrm:views/prima-nota/list', [
    'nonprofit-espocrm:views/record/list-inline-edit',
    'nonprofit-espocrm:views/reporting/list-stats-footer',
    'nonprofit-espocrm:lib/reporting-list-export',
], function (Dep, ListStatsFooter, ReportingListExport) {

    return Dep.extend(Object.assign({}, ReportingListExport, {

        setup() {
            Dep.prototype.setup.apply(this, arguments);

            this.statsFooter = new ListStatsFooter(this);
            this.reportingStats = null;

            this.listenTo(this.collection, 'sync', () => {
                this.loadReportingStats();

                if (this.isRendered()) {
                    this.applyAmountColors();
                }
            });

            this.loadReportingStats();
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.renderReportingStats();
            this.applyAmountColors();
        },

        applyAmountColors() {
            const self = this;

            this.$el.find('tr.list-row').each(function () {
                const modelId = $(this).data('id');
                const model = self.collection.get(modelId);

                if (!model) {
                    return;
                }

                const entryType = model.get('entryType');
                const cssClass = entryType === 'Expense'
                    ? 'prima-nota-amount-out'
                    : 'prima-nota-amount-in';

                $(this).find('td[data-name="amount"], td[data-name="entryType"]')
                    .removeClass('prima-nota-amount-in prima-nota-amount-out')
                    .addClass(cssClass);

                $(this).find('td[data-name="amount"] .cell, td[data-name="entryType"] .cell, td[data-name="entryType"] .field')
                    .removeClass('prima-nota-amount-in prima-nota-amount-out')
                    .addClass(cssClass);
            });
        },

        loadReportingStats() {
            if (this.hasActiveFilters()) {
                Espo.Ajax.postRequest(
                    'NonprofitEspocrm/reporting/prima-nota/totals',
                    this.buildTotalsSearchPayload()
                )
                    .then(data => {
                        this.reportingStats = {
                            mode: 'selection',
                            metricList: data.metricList || [],
                            selection: data,
                        };

                        if (this.isRendered()) {
                            this.renderReportingStats();
                        }
                    })
                    .catch(() => {
                        this.reportingStats = null;
                    });

                return;
            }

            Espo.Ajax.getRequest('NonprofitEspocrm/reporting/prima-nota/summary')
                .then(data => {
                    this.reportingStats = {
                        ...data,
                        mode: 'period',
                    };

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

            const metricList = this.reportingStats.metricList ||
                ['amountIn', 'amountOut', 'managementBalance'];

            const items = metricList.map(key => ({
                ...this.statsFooter.resolveMetricItem('PrimaNota', key === 'managementBalance' ? 'amountIn' : key),
                key: key,
                fieldType: key === 'managementBalance' ? 'currency' : (this.getMetadata().get(['entityDefs', 'PrimaNota', 'fields', key, 'type']) || 'currency'),
                currency: 'EUR',
                label: this.translate(key, 'fields', 'PrimaNota'),
            }));

            if (!items.length) {
                return;
            }

            if (this.reportingStats.mode === 'selection') {
                this.statsFooter.renderSelection($container, this.reportingStats.selection || {}, {
                    items,
                });

                return;
            }

            this.statsFooter.renderPeriodGrid($container, this.reportingStats, {items});
        },

        getReportingStatsContainer() {
            const $parent = this.$el.parent('.list-container');

            if ($parent.length) {
                return $parent;
            }

            return this.$el;
        },

        getListParentView() {
            const parent = this.getParentView();

            return parent && parent.name === 'List' ? parent : null;
        },

        hasActiveFilters() {
            const listView = this.getListParentView();

            if (listView && listView.searchManager) {
                const data = listView.searchManager.get();

                if (data.textFilter && String(data.textFilter).trim() !== '') {
                    return true;
                }

                if (data.primary) {
                    return true;
                }

                if (data.advanced) {
                    for (const name in data.advanced) {
                        if (data.advanced[name]) {
                            return true;
                        }
                    }
                }

                if (data.bool) {
                    for (const name in data.bool) {
                        if (data.bool[name]) {
                            return true;
                        }
                    }
                }

                return false;
            }

            return (this.collection.where || []).length > 0;
        },

        buildTotalsSearchPayload() {
            const listView = this.getListParentView();

            if (listView && listView.searchManager) {
                const data = listView.searchManager.get();

                return {
                    where: listView.searchManager.getWhere(),
                    textFilter: data.textFilter || '',
                    primaryFilter: data.primary || null,
                    boolFilterList: Object.keys(data.bool || {}).filter(name => data.bool[name]),
                    orderBy: this.collection.orderBy,
                    order: this.collection.order,
                };
            }

            return {
                where: this.collection.where || [],
                orderBy: this.collection.orderBy,
                order: this.collection.order,
            };
        },
    }));
});
