define('google-integration:views/fields/google-calendar-color', [
    'exports',
    'views/fields/enum',
    'google-integration:lib/google-calendar-color-swatch',
], function (_exports, _enum, ColorSwatch) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _enum = _interopRequireDefault(_enum);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class GoogleCalendarColorField extends _enum.default {
        afterRender() {
            if (this.mode === 'edit') {
                ColorSwatch.markSelectOptions(this.$el.find('select'));
            }

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
        }

        decorateDetail() {
            if (this.mode === 'edit') {
                return;
            }

            const value = String(this.model.get(this.name) || '');
            const $field = this.$el.find('.field').first();
            const $target = $field.length ? $field : this.$el;

            $target.find('.google-calendar-color-swatch').remove();
            ColorSwatch.createSwatch(value)
                .css({marginRight: '6px', verticalAlign: 'middle'})
                .prependTo($target);
        }

        decorateSelectize() {
            ColorSwatch.decorateSelectize(this.$el, () => this.model.get(this.name));
        }
    }

    _exports.default = GoogleCalendarColorField;
});
