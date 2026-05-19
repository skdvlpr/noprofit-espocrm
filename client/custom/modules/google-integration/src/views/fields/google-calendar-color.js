define('google-integration:views/fields/google-calendar-color', ['exports', 'views/fields/enum'], function (_exports, _enum) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _enum = _interopRequireDefault(_enum);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const COLOR_MAP = {
        '': '#9aa0a6',
        '1': '#7986cb',
        '2': '#33b679',
        '3': '#8e24aa',
        '4': '#e67c73',
        '5': '#f6c026',
        '6': '#f4511e',
        '7': '#039be5',
        '8': '#616161',
        '9': '#3f51b5',
        '10': '#0b8043',
        '11': '#d50000',
    };

    class GoogleCalendarColorField extends _enum.default {
        afterRender() {
            super.afterRender();

            this.decorate();
            window.setTimeout(() => this.decorate(), 0);
        }

        decorate() {
            if (!this.$el) {
                return;
            }

            this.decorateDetail();
            this.decorateSelectize();
            this.decorateNativeSelect();
        }

        decorateDetail() {
            if (this.mode === 'edit') {
                return;
            }

            const value = String(this.model.get(this.name) || '');
            const $field = this.$el.find('.field').first();
            const $target = $field.length ? $field : this.$el;

            $target.find('.google-calendar-color-swatch').remove();
            this.createSwatch(value)
                .css({marginRight: '6px', verticalAlign: 'middle'})
                .prependTo($target);
        }

        decorateSelectize() {
            const select = this.$el.find('select').get(0);
            const selectize = select ? select.selectize : null;

            if (!selectize) {
                return;
            }

            const decorate = () => {
                this.decorateSelectizeInput();
                this.decorateSelectizeDropdown();
            };

            decorate();

            if (!selectize.googleCalendarColorDecorated) {
                selectize.googleCalendarColorDecorated = true;
                selectize.on('dropdown_open', decorate);
                selectize.on('change', decorate);
            }
        }

        decorateSelectizeInput() {
            const value = String(this.model.get(this.name) || '');
            const $input = this.$el.find('.selectize-input').first();

            if (!$input.length) {
                return;
            }

            $input.find('.google-calendar-color-swatch').remove();
            this.createSwatch(value)
                .css({marginRight: '6px', verticalAlign: 'middle'})
                .prependTo($input);
        }

        decorateSelectizeDropdown() {
            this.$el.find('.selectize-dropdown-content [data-value]').each((i, element) => {
                const $option = $(element);
                const value = String($option.attr('data-value') || '');

                $option.find('.google-calendar-color-swatch').remove();
                this.createSwatch(value)
                    .css({marginRight: '8px', verticalAlign: 'middle'})
                    .prependTo($option);
            });
        }

        decorateNativeSelect() {
            const $select = this.$el.find('select');

            if (!$select.length) {
                return;
            }

            $select.find('option').each((i, option) => {
                const $option = $(option);
                const value = String($option.attr('value') || '');

                $option.css({
                    backgroundColor: COLOR_MAP[value] || COLOR_MAP[''],
                    color: this.needsLightText(value) ? '#fff' : '#111',
                });
            });
        }

        createSwatch(value) {
            return $('<span>')
                .addClass('google-calendar-color-swatch')
                .css({
                    display: 'inline-block',
                    width: '12px',
                    height: '12px',
                    borderRadius: '50%',
                    backgroundColor: COLOR_MAP[value] || COLOR_MAP[''],
                    border: '1px solid rgba(255, 255, 255, 0.55)',
                    boxShadow: '0 0 0 1px rgba(0, 0, 0, 0.18)',
                });
        }

        needsLightText(value) {
            return ['3', '6', '8', '9', '10', '11'].includes(value);
        }
    }

    _exports.default = GoogleCalendarColorField;
});
