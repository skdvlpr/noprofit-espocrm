define('google-integration:views/fields/google-calendar-date-source-list', ['exports', 'views/fields/multi-enum'], function (_exports, _multiEnum) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _multiEnum = _interopRequireDefault(_multiEnum);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class GoogleCalendarDateSourceListField extends _multiEnum.default {
        setup() {
            super.setup();
            this.sourceOptionList = [];

            this.listenTo(this.model, 'change:googleCalendarDateSourceList', () => {
                this.model.trigger('change:googleCalendarEventSettings');
            });

            this.loadSourceOptions();
        }

        loadSourceOptions() {
            Espo.Ajax.getRequest(`GoogleIntegration/calendar/date-source-options/${this.model.entityType}`)
                .then(data => {
                    this.sourceOptionList = Array.isArray(data.sources) ? data.sources : [];
                    this.params.options = this.sourceOptionList.map(item => item.sourceDateType);
                    this.translatedOptions = this.buildTranslatedOptions();

                    if (this.isRendered()) {
                        this.reRender();
                    }
                })
                .catch(() => {
                    this.sourceOptionList = [];
                    this.params.options = [];
                    this.translatedOptions = {};
                });
        }

        buildTranslatedOptions() {
            const map = {};

            this.sourceOptionList.forEach(item => {
                const value = item.sourceDateType;
                const label = item.label || value;
                map[value] = label;
            });

            return map;
        }

        translateOption(value) {
            if (this.translatedOptions && this.translatedOptions[value]) {
                return this.translatedOptions[value];
            }

            return super.translateOption(value);
        }
    }

    _exports.default = GoogleCalendarDateSourceListField;
});
