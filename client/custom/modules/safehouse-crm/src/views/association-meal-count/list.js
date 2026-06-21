define('safehouse-crm:views/association-meal-count/list', [
    'safehouse-crm:views/record/list-inline-edit',
    'safehouse-crm:views/reporting/list-stats-footer',
], function (Dep, ListStatsFooter) {

    const SUMMARY_URL = 'SafehouseCrm/reporting/association-meal-count/summary';
    const TOTALS_URL = 'SafehouseCrm/reporting/association-meal-count/totals';
    const ENTITY_SCOPE = 'AssociationMealCount';
    const DEFAULT_METRICS = ['portionCount'];

    return Dep.extend({

        setup() {
            Dep.prototype.setup.apply(this, arguments);

            this.statsFooter = new ListStatsFooter(this);
            this.reportingStats = null;

            this.listenTo(this.collection, 'sync', () => this.loadReportingStats());
            this.loadReportingStats();
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.renderReportingStats();
        },

        loadReportingStats() {
            if (this.hasActiveFilters()) {
                Espo.Ajax.postRequest(TOTALS_URL, this.buildTotalsSearchPayload())
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

            Espo.Ajax.getRequest(SUMMARY_URL)
                .then(data => {
                    this.reportingStats = {...data, mode: 'period'};

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

            const metricList = this.reportingStats.metricList || DEFAULT_METRICS;
            const items = metricList.map(key => ({
                key: key,
                label: this.translate(key, 'fields', ENTITY_SCOPE),
            }));

            if (!items.length) {
                return;
            }

            if (this.reportingStats.mode === 'selection') {
                $container.find('.safehouse-reporting-stats-week, .safehouse-reporting-stats-month, .safehouse-reporting-stats-year').remove();

                this.statsFooter.render($container, this.reportingStats.selection || {}, {
                    title: this.translate('reportingListStatsSelection', 'labels', 'Global'),
                    items,
                    removeSelector: '.safehouse-reporting-stats-selection',
                    extraClass: 'safehouse-reporting-stats-selection',
                });

                return;
            }

            $container.find('.safehouse-reporting-stats-selection').remove();

            const week = this.reportingStats.week || {};
            const month = this.reportingStats.month || {};
            const year = this.reportingStats.year || {};

            this.statsFooter.render($container, week, {
                title: this.translate('reportingListStatsWeek', 'labels', 'Global')
                    + (week.from && week.to ? ` (${week.from} – ${week.to})` : ''),
                items,
                removeSelector: '.safehouse-reporting-stats-week',
                extraClass: 'safehouse-reporting-stats-week',
            });

            this.statsFooter.render($container, month, {
                title: this.translate('reportingListStatsMonth', 'labels', 'Global')
                    + (month.from && month.to ? ` (${month.from} – ${month.to})` : ''),
                items,
                removeSelector: '.safehouse-reporting-stats-month',
                extraClass: 'safehouse-reporting-stats-month',
            });

            this.statsFooter.render($container, year, {
                title: this.translate('reportingListStatsYear', 'labels', 'Global')
                    + (year.from && year.to ? ` (${year.from} – ${year.to})` : ''),
                items,
                removeSelector: '.safehouse-reporting-stats-year',
                extraClass: 'safehouse-reporting-stats-year',
            });
        },

        getReportingStatsContainer() {
            const $parent = this.$el.parent('.list-container');

            if ($parent.length) {
                return $parent;
            }

            let $ancestor = this.$el.parent();

            while ($ancestor.length && !$ancestor.is('body')) {
                if ($ancestor.hasClass('list-container')) {
                    return $ancestor;
                }

                $ancestor = $ancestor.parent();
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
    });
});
