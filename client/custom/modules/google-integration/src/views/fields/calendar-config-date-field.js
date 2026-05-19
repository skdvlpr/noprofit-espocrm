define('google-integration:views/fields/calendar-config-date-field', ['exports', 'views/fields/enum'], function (_exports, _enum) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _enum = _interopRequireDefault(_enum);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class CalendarConfigDateField extends _enum.default {
        setup() {
            super.setup();

            this.listenTo(this.model, 'change:targetEntityType', () => {
                if (this.name === 'dateField') {
                    const options = this.getDateFieldOptions(this.model.get('targetEntityType'));
                    const current = this.model.get('dateField');

                    if (current && !options.includes(current)) {
                        this.model.set({
                            dateField: options[0] || null,
                            endDateField: null,
                        }, {ui: true});
                    }
                }
            });
        }

        setupOptions() {
            const entityType = this.model.get('targetEntityType');
            const fields = entityType
                ? this.getMetadata().get(`entityDefs.${entityType}.fields`) || {}
                : {};

            this.params.options = Object.keys(fields)
                .filter(name => {
                    const type = fields[name]?.type;
                    return type === 'date' || type === 'datetime';
                })
                .sort();

            if (this.name === 'endDateField') {
                this.params.options = ['', ...this.params.options];
            }

            this.translatedOptions = {};

            this.params.options.forEach(value => {
                this.translatedOptions[value] = this.translateOption(value);
            });

            super.setupOptions();
        }

        translateOption(value) {
            if (!value) {
                return this.translate('None');
            }

            const entityType = this.model.get('targetEntityType');

            if (!entityType) {
                return value;
            }

            const translated = this.getLanguage().translate(value, 'fields', entityType);

            if (translated && translated !== value) {
                return translated;
            }

            return value
                .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
                .replace(/^./, letter => letter.toUpperCase());
        }
    }

    _exports.default = CalendarConfigDateField;
});
