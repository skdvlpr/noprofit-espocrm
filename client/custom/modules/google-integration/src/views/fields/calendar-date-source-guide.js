define('google-integration:views/fields/calendar-date-source-guide', ['exports', 'views/fields/base'], function (_exports, _base) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _base = _interopRequireDefault(_base);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class CalendarDateSourceGuideField extends _base.default {
        detailTemplateContent = `
            <div class="google-calendar-date-source-guide panel panel-default" style="margin-bottom: 0;">
                <div class="panel-body" style="padding: 12px 14px;" data-role="guide-html"></div>
            </div>
        `;

        editTemplateContent = `
            <div class="google-calendar-date-source-guide panel panel-default" style="margin-bottom: 0;">
                <div class="panel-body" style="padding: 12px 14px;" data-role="guide-html"></div>
            </div>
        `;

        data() {
            return {};
        }

        fetch() {
            return {};
        }

        afterRender() {
            super.afterRender();

            const markdown = this.translate('calendarDateSourceSetupGuide', 'labels', 'CalendarDateSource');
            const html = this.getHelper().transformMarkdownText(markdown).toString();

            const $container = this.$el.find('[data-role="guide-html"]');

            $container.html(html);

            // Keep markdown tables/code readable across light & dark themes.
            $container.find('table').css({
                width: '100%',
                borderCollapse: 'collapse',
            });

            $container.find('th, td').css({
                border: '1px solid var(--border-soft-color, rgba(127, 127, 127, 0.25))',
                padding: '6px 8px',
                background: 'transparent',
            });

            $container.find('pre').css({
                background: 'var(--dropdown-bg, rgba(127, 127, 127, 0.08))',
                border: '1px solid var(--border-soft-color, rgba(127, 127, 127, 0.25))',
                color: 'inherit',
                padding: '6px 8px',
                borderRadius: '4px',
            });

            $container.find('code').css({
                background: 'rgba(127, 127, 127, 0.15)',
                color: 'inherit',
                padding: '1px 4px',
                borderRadius: '3px',
            });
        }
    }

    _exports.default = CalendarDateSourceGuideField;
});
