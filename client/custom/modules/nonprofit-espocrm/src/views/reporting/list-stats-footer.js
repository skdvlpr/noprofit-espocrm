define('nonprofit-espocrm:views/reporting/list-stats-footer', ['exports'], function (_exports) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    const PERIOD_ORDER = [
        {key: 'year', labelKey: 'reportingListStatsYear'},
        {key: 'month', labelKey: 'reportingListStatsMonth'},
        {key: 'today', labelKey: 'reportingListStatsToday'},
    ];

    /**
     * Shared list / dashlet renderer for Rendicontazione reporting (Task 7.3).
     */
    class ListStatsFooter {
        constructor(view) {
            this.view = view;
        }

        /**
         * Year | Month | Today responsive grid (Anno / Mese / Oggi).
         *
         * @param {jQuery} $container
         * @param {object} summary API summary payload (year, month, today)
         * @param {object} options { items: [{ key, label }] }
         */
        renderPeriodGrid($container, summary, options = {}) {
            if (!$container || !$container.length) {
                return;
            }

            $container.find('.safehouse-reporting-stats-period-grid, .safehouse-reporting-stats-selection').remove();

            const items = options.items || [];

            if (!items.length) {
                return;
            }

            const $grid = $('<div>').addClass('safehouse-reporting-stats-period-grid margin-bottom');

            PERIOD_ORDER.forEach(period => {
                const stats = summary[period.key] || {};
                const title = this.view.translate(period.labelKey, 'labels', 'Global');
                const range = this.formatPeriodRange(stats.from, stats.to);

                const $cell = $('<div>').addClass('safehouse-reporting-stats-period-cell');
                $cell.append($('<div>').addClass('safehouse-reporting-stats-period-title').text(title));

                if (range) {
                    $cell.append($('<div>').addClass('safehouse-reporting-stats-period-range').text(range));
                }

                const $metrics = $('<div>').addClass('safehouse-reporting-stats-metrics');

                items.forEach(item => {
                    const value = stats[item.key];
                    const label = item.label || item.key;
                    const formatted = this.formatValue(value, item);

                    $metrics.append(
                        $('<div>').addClass('safehouse-reporting-stats-metric')
                            .append(
                                $('<span>')
                                    .addClass('safehouse-reporting-stats-value safehouse-reporting-stats-value--accent')
                                    .text(formatted)
                            )
                            .append(
                                $('<span>')
                                    .addClass('safehouse-reporting-stats-label')
                                    .text(label)
                            )
                    );
                });

                $cell.append($metrics);
                $grid.append($cell);
            });

            $container.prepend($grid);
        }

        /**
         * Filtered selection totals (single full-width banner).
         *
         * @param {jQuery} $container
         * @param {object} stats
         * @param {object} options
         */
        renderSelection($container, stats, options = {}) {
            if (!$container || !$container.length) {
                return;
            }

            $container.find('.safehouse-reporting-stats-period-grid, .safehouse-reporting-stats-selection').remove();

            const items = options.items || [];
            const title = options.title ||
                this.view.translate('reportingListStatsSelection', 'labels', 'Global');

            const $footer = $('<div>')
                .addClass('safehouse-reporting-stats-selection safehouse-reporting-stats-period-cell margin-bottom');

            $footer.append($('<div>').addClass('safehouse-reporting-stats-period-title').text(title));

            const $metrics = $('<div>').addClass('safehouse-reporting-stats-metrics safehouse-reporting-stats-metrics--inline');

            items.forEach(item => {
                const value = stats[item.key];
                const label = item.label || item.key;
                const formatted = this.formatValue(value, item);

                $metrics.append(
                    $('<div>').addClass('safehouse-reporting-stats-metric')
                        .append(
                            $('<span>')
                                .addClass('safehouse-reporting-stats-value safehouse-reporting-stats-value--accent')
                                .text(formatted)
                        )
                        .append(
                            $('<span>').addClass('safehouse-reporting-stats-label').text(label)
                        )
                );
            });

            $footer.append($metrics);
            $container.prepend($footer);
        }

        formatPeriodRange(from, to) {
            if (!from) {
                return '';
            }

            if (!to || from === to) {
                return from;
            }

            return `${from} – ${to}`;
        }

        resolveMetricItem(scope, key) {
            const fieldDef = this.view.getMetadata().get(['entityDefs', scope, 'fields', key]) || {};

            return {
                key: key,
                fieldType: fieldDef.type || null,
                currency: fieldDef.currency || null,
            };
        }

        formatValue(value, item = {}) {
            if (item.fieldType === 'currency') {
                return this.formatCurrencyValue(value, item.currency);
            }

            if (value === null || value === undefined) {
                return '0';
            }

            if (typeof value === 'number') {
                return Number.isInteger(value) ? String(value) : value.toFixed(2);
            }

            return String(value);
        }

        formatCurrencyValue(value, currencyCode) {
            const config = this.view.getConfig();
            const currency = currencyCode || config.get('defaultCurrency') || 'EUR';
            const symbol = this.view.getMetadata().get(['app', 'currency', 'symbolMap', currency]) || currency;
            const decimalPlaces = config.get('currencyDecimalPlaces');
            const maxDecimalPlaces = 3;
            const format = config.get('currencyFormat') || 2;
            const thousandSeparator = config.get('thousandSeparator') || ',';
            const decimalMark = config.get('decimalMark') || '.';

            let num = Number(value);

            if (value === null || value === undefined || Number.isNaN(num)) {
                num = 0;
            }

            if (decimalPlaces === 0) {
                num = Math.round(num);
            } else if (decimalPlaces) {
                num = Math.round(num * Math.pow(10, decimalPlaces)) / Math.pow(10, decimalPlaces);
            } else {
                num = Math.round(num * Math.pow(10, maxDecimalPlaces)) / Math.pow(10, maxDecimalPlaces);
            }

            const parts = num.toString().split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);

            if (decimalPlaces === 0) {
                // integer currency
            } else if (decimalPlaces) {
                if (parts.length === 1) {
                    parts[1] = '';
                }

                while (parts[1].length < decimalPlaces) {
                    parts[1] += '0';
                }
            }

            const formatted = decimalPlaces === 0 ? parts[0] : parts.join(decimalMark);

            if (format === 1) {
                return `${formatted} ${currency}`;
            }

            if (format === 3) {
                return `${formatted} ${symbol}`;
            }

            return `${symbol}${formatted}`;
        }

        clear($container) {
            if ($container && $container.length) {
                $container.find(
                    '.safehouse-reporting-stats-period-grid, .safehouse-reporting-stats-selection'
                ).remove();
            }
        }
    }

    ListStatsFooter.PERIOD_ORDER = PERIOD_ORDER;

    _exports.default = ListStatsFooter;
});
