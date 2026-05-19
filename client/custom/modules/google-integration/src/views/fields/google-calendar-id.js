define('google-integration:views/fields/google-calendar-id', ['exports', 'views/fields/enum'], function (_exports, _enum) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _enum = _interopRequireDefault(_enum);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class GoogleCalendarIdField extends _enum.default {
        normalizeCalendarId(value) {
            return value === 'googleCalendarPrimary' ? 'primary' : value;
        }

        translateLabelKey(key) {
            return this.translate(key, 'fields', this.entityType)
                || this.translate(key, 'fields', 'Meeting')
                || this.translate(key, 'fields', 'Call')
                || this.translate(key, 'fields', 'Task')
                || this.translate(key, 'fields', 'Opportunity');
        }

        setup() {
            super.setup();
            this.calendarOptionList = [];
            this.calendarLoaded = false;
            this.connectHintShown = false;
        }

        setupOptions() {
            const current = this.normalizeCalendarId(this.model.get(this.name));

            if (current !== this.model.get(this.name)) {
                this.model.set(this.name, current, {silent: true});
            }

            if (!this.calendarLoaded) {
                this.params.options = current ? [String(current)] : ['primary'];
                this.translatedOptions = {
                    primary: this.translateLabelKey('googleCalendarPrimary') || 'primary',
                };

                if (current && current !== 'primary') {
                    this.translatedOptions[current] = current;
                }

                super.setupOptions();
                this.loadCalendars();

                return;
            }

            this.params.options = this.calendarOptionList.map(item => item.id);
            this.translatedOptions = {};

            this.calendarOptionList.forEach(item => {
                this.translatedOptions[item.id] = item.summary || item.id;
            });

            super.setupOptions();
        }

        afterRender() {
            super.afterRender();
            this.decorateLabels();
        }

        loadCalendars() {
            Espo.Ajax.getRequest('GoogleIntegration/calendar/google-calendars')
                .then(data => {
                    const list = Array.isArray(data.list) ? data.list : [];
                    this.calendarOptionList = list.length
                        ? list
                        : [{id: 'primary', summary: this.translateLabelKey('googleCalendarPrimary') || 'primary'}];
                    this.calendarLoaded = true;

                    if (data.connected === false && !this.connectHintShown) {
                        this.connectHintShown = true;
                        Espo.Ui.info(
                            this.translateLabelKey('googleCalendarConnectAccountHint'),
                            true
                        );
                    }

                    this.reRender();
                })
                .catch(() => {
                    this.calendarOptionList = [{
                        id: 'primary',
                        summary: this.translateLabelKey('googleCalendarPrimary') || 'primary',
                    }];
                    this.calendarLoaded = true;
                    this.reRender();
                });
        }

        decorateLabels() {
            const map = {};
            this.calendarOptionList.forEach(item => {
                map[item.id] = item.summary || item.id;
            });

            this.$el.find('option').each((i, element) => {
                const id = element.value;

                if (map[id]) {
                    element.textContent = map[id];
                }
            });

            const select = this.$el.find('select').get(0);
            const selectize = select ? select.selectize : null;

            if (selectize) {
                Object.keys(selectize.options).forEach(id => {
                    if (map[id]) {
                        selectize.options[id].text = map[id];
                    }
                });
                selectize.refreshOptions(false);
            }
        }
    }

    _exports.default = GoogleCalendarIdField;
});
