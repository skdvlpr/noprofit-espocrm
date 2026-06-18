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
            return this.translate(key, 'labels', 'Global');
        }

        setup() {
            super.setup();
            this.calendarOptionList = [];
            this.calendarLoaded = false;
            this.calendarLoadPending = false;
            this.connectHintShown = false;
            this.needsCalendarRefresh = false;
        }

        setupOptions() {
            const current = this.normalizeCalendarId(this.model.get(this.name));

            if (current !== this.model.get(this.name)) {
                this.model.set(this.name, current, {silent: true});
            }

            if (!this.calendarLoaded) {
                this.params.options = current ? [String(current)] : ['primary'];
                this.translatedOptions = {
                    primary: this.translateLabelKey('googleCalendarPrimary'),
                };

                if (current && current !== 'primary') {
                    this.translatedOptions[current] = current;
                }

                super.setupOptions();
                this.loadCalendars();

                return;
            }

            this.applyOptionParams();
            super.setupOptions();
        }

        applyOptionParams() {
            const current = this.normalizeCalendarId(this.model.get(this.name));

            this.params.options = this.calendarOptionList.map(item => item.id);
            this.translatedOptions = {};

            this.calendarOptionList.forEach(item => {
                this.translatedOptions[item.id] = item.summary || item.id;
            });

            if (current && !this.params.options.includes(current)) {
                this.params.options.unshift(current);
                this.translatedOptions[current] = this.translatedOptions[current] || current;
            }
        }

        afterRender() {
            super.afterRender();

            if (this.needsCalendarRefresh) {
                this.needsCalendarRefresh = false;
                this.applyCalendarOptions();
            }

            this.decorateLabels();
        }

        isFieldVisible() {
            if (!this.$el || !this.$el.length) {
                return false;
            }

            if (this.$el.hasClass('hidden')) {
                return false;
            }

            return !this.$el.closest('.hidden-cell').length;
        }

        getSelectize() {
            const select = this.$el.find('select').get(0);

            return select && select.selectize ? select.selectize : null;
        }

        ensureCalendarsLoaded() {
            if (!this.calendarLoaded && !this.calendarLoadPending) {
                this.loadCalendars();
                return;
            }

            if (this.calendarLoaded) {
                this.applyCalendarOptions();
            }
        }

        reloadCalendars() {
            this.calendarLoaded = false;
            this.calendarLoadPending = false;
            this.loadCalendars();
        }

        applyCalendarOptions() {
            if (!this.calendarLoaded || !this.calendarOptionList.length) {
                return;
            }

            this.applyOptionParams();

            if (!this.isRendered()) {
                return;
            }

            const current = this.normalizeCalendarId(this.model.get(this.name)) || 'primary';
            const selectize = this.getSelectize();

            if (selectize) {
                selectize.clearOptions();

                this.params.options.forEach(id => {
                    selectize.addOption({
                        value: id,
                        text: this.translatedOptions[id] || id,
                    });
                });

                if (this.params.options.includes(current)) {
                    selectize.setValue(current, true);
                }

                selectize.refreshOptions(false);
                this.decorateLabels();

                return;
            }

            this.reRender();
        }

        loadCalendars() {
            if (this.calendarLoadPending) {
                return;
            }

            this.calendarLoadPending = true;

            Espo.Ajax.getRequest('GoogleIntegration/calendar/google-calendars')
                .then(data => {
                    this.calendarLoadPending = false;

                    const list = Array.isArray(data.list) ? data.list : [];
                    this.calendarOptionList = list.length
                        ? list.map(item => ({
                            id: String(item.id || ''),
                            summary: String(item.summary || item.id || ''),
                        })).filter(item => item.id !== '')
                        : [{
                            id: 'primary',
                            summary: this.translateLabelKey('googleCalendarPrimary'),
                        }];
                    this.calendarLoaded = true;

                    if (data.connected === false && !this.connectHintShown) {
                        this.connectHintShown = true;
                        const reason = data.errorReason || 'unknown';
                        let message = this.translateLabelKey('googleCalendarListFailedHint');

                        if (reason === 'not_connected') {
                            message = this.translateLabelKey('googleCalendarConnectAccountHint');
                        } else if (data.errorMessage) {
                            message = `${message} (${data.errorMessage})`;
                        }

                        Espo.Ui.warning(message, true);
                    }

                    if (this.isFieldVisible()) {
                        this.applyCalendarOptions();
                    } else {
                        this.needsCalendarRefresh = true;
                    }
                })
                .catch(() => {
                    this.calendarLoadPending = false;
                    this.calendarOptionList = [{
                        id: 'primary',
                        summary: this.translateLabelKey('googleCalendarPrimary'),
                    }];
                    this.calendarLoaded = true;

                    if (!this.connectHintShown) {
                        this.connectHintShown = true;
                        Espo.Ui.warning(
                            this.translateLabelKey('googleCalendarListFailedHint'),
                            true
                        );
                    }

                    if (this.isFieldVisible()) {
                        this.applyCalendarOptions();
                    } else {
                        this.needsCalendarRefresh = true;
                    }
                });
        }

        decorateLabels() {
            const map = {};
            this.calendarOptionList.forEach(item => {
                map[item.id] = item.summary || item.id;
            });

            map.primary = map.primary || this.translateLabelKey('googleCalendarPrimary');

            this.$el.find('option').each((i, element) => {
                const id = element.value;

                if (map[id]) {
                    element.textContent = map[id];
                }
            });

            const selectize = this.getSelectize();

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
