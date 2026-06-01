define('google-integration:views/fields/calendar-config-date-field', ['exports', 'views/fields/enum'], function (_exports, _enum) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _enum = _interopRequireDefault(_enum);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const AUDIT_DATE_FIELDS = ['createdAt', 'modifiedAt', 'deletedAt'];

    /** Espo CRM all-day companion fields (Task/Meeting/Call) — not for CalendarDateSource. */
    const DATETIME_COMPANION_FIELD = {
        dateStartDate: 'dateStart',
        dateEndDate: 'dateEnd',
    };

    class CalendarConfigDateField extends _enum.default {
        setup() {
            this.listenTo(this.model, 'change:targetEntityType', () => {
                this.setupOptions();

                if (this.name === 'dateField') {
                    const options = this.params.options || [];
                    const current = this.model.get('dateField');

                    if (!current || !options.includes(current)) {
                        this.model.set({
                            dateField: options[0] || null,
                            endDateField: null,
                        }, {ui: true});
                    }
                }

                if (this.isRendered()) {
                    this.reRender();
                }
            });

            super.setup();
        }

        getSelectableDateFieldNames(entityType) {
            if (!entityType) {
                return [];
            }

            const fields = this.getMetadata().get(`entityDefs.${entityType}.fields`) || {};
            const business = [];
            const fallback = [];

            Object.keys(fields).forEach(name => {
                const fieldDef = fields[name];
                const type = fieldDef?.type;

                if (type !== 'date' && type !== 'datetime' && type !== 'datetimeOptional') {
                    return;
                }

                // Espo CRM utility companions (e.g. Task dateStartDate) duplicate datetime fields
                // with "(all day)" labels — use the main field + the allDay checkbox instead.
                if (fieldDef?.utility) {
                    return;
                }

                const companionOf = DATETIME_COMPANION_FIELD[name];

                if (companionOf && fields[companionOf]) {
                    return;
                }

                if (AUDIT_DATE_FIELDS.includes(name)) {
                    fallback.push(name);

                    return;
                }

                business.push(name);
            });

            return (business.length ? business : fallback).sort();
        }

        setupOptions() {
            const entityType = this.model.get('targetEntityType');
            const optionDataList = this.getSelectableDateFieldNames(entityType)
                .map(name => ({
                    value: name,
                    label: this.translateOption(name, entityType),
                }))
                .filter(item => item.label);

            this.params.options = optionDataList.map(item => item.value);

            if (this.name === 'endDateField') {
                this.params.options = ['', ...this.params.options];
            }

            this.translatedOptions = {};

            if (this.name === 'endDateField') {
                this.translatedOptions[''] = this.translate('None');
            }

            optionDataList.forEach(item => {
                this.translatedOptions[item.value] = item.label;
            });

            super.setupOptions();
        }

        translateOption(value, entityType = null) {
            if (!value) {
                return this.translate('None');
            }

            if (!entityType) {
                return '';
            }

            const translated = this.getLanguage().translate(value, 'fields', entityType);
            return translated && translated !== value ? translated : '';
        }
    }

    _exports.default = CalendarConfigDateField;
});
