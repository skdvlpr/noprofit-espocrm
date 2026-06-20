define('safehouse-crm:views/reporting/list-stats-footer', ['exports'], function (_exports) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    /**
     * Shared list footer / banner renderer for Rendicontazione reporting (Task 7.3).
     *
     * @param {object} view Bullbone view (list record)
     * @param {object} stats keyed totals, e.g. { adults, minors, foodCost }
     * @param {object} options { title, items: [{ key, label }] }
     */
    class ListStatsFooter {
        constructor(view) {
            this.view = view;
        }

        /**
         * @param {jQuery} $container
         * @param {object} stats
         * @param {object} options
         */
        render($container, stats, options = {}) {
            if (!$container || !$container.length) {
                return;
            }

            $container.find('.safehouse-reporting-stats-footer').remove();

            const items = options.items || [];
            const title = options.title || this.view.translate('reportingListStatsTitle', 'labels', 'Global');

            const $footer = $('<div>')
                .addClass('safehouse-reporting-stats-footer alert alert-info margin-bottom');

            $footer.append($('<strong>').addClass('safehouse-reporting-stats-title').text(title));

            const $grid = $('<div>').addClass('safehouse-reporting-stats-grid');

            items.forEach(item => {
                const value = stats[item.key];
                const label = item.label || item.key;
                const formatted = this.formatValue(value);

                $grid.append(
                    $('<div>').addClass('safehouse-reporting-stats-item')
                        .append($('<span>').addClass('safehouse-reporting-stats-label').text(label + ': '))
                        .append($('<span>').addClass('safehouse-reporting-stats-value').text(formatted))
                );
            });

            $footer.append($grid);
            $container.prepend($footer);
        }

        formatValue(value) {
            if (value === null || value === undefined) {
                return '0';
            }

            if (typeof value === 'number') {
                return Number.isInteger(value) ? String(value) : value.toFixed(2);
            }

            return String(value);
        }

        clear($container) {
            if ($container && $container.length) {
                $container.find('.safehouse-reporting-stats-footer').remove();
            }
        }
    }

    _exports.default = ListStatsFooter;
});
