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
            this.createNewCalendar = false;
            this.newCalendarName = '';
            this.createInProgress = false;
            // Admin-only Integrations → Google prefix/suffix (C). Not user-editable here —
            // shown as a read-only naming preview only.
            this.calendarNamePrefix = 'CRM';
            this.calendarNameSuffix = '';
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
            this.renderCreateNewUi();
            this.syncCreateNewUiState();
        }

        renderCreateNewUi() {
            if (this.mode !== 'edit') {
                return;
            }

            this.$el.find('.google-calendar-create-new').remove();

            const $wrap = $('<div>')
                .addClass('google-calendar-create-new margin-top');

            const $checkLabel = $('<label>')
                .addClass('checkbox-inline')
                .append(
                    $('<input>')
                        .attr({
                            type: 'checkbox',
                            'data-role': 'create-new-calendar',
                        })
                        .prop('checked', this.createNewCalendar),
                    ' ',
                    document.createTextNode(this.translateLabelKey('googleCalendarCreateNew'))
                );

            const $nameRow = $('<div>')
                .addClass('google-calendar-create-new-name margin-top')
                .toggleClass('hidden', !this.createNewCalendar);

            const $input = $('<input>')
                .addClass('form-control')
                .attr({
                    type: 'text',
                    maxlength: 200,
                    placeholder: this.translateLabelKey('googleCalendarCreateNewNamePlaceholder'),
                    'data-role': 'new-calendar-name',
                })
                .val(this.newCalendarName || this.defaultNewCalendarName());

            const $createBtn = $('<button>')
                .attr({type: 'button'})
                .addClass('btn btn-default btn-sm margin-top')
                .attr('data-role', 'create-calendar-submit')
                .text(this.translateLabelKey('googleCalendarCreateNewSubmit'));

            const $help = $('<p>')
                .addClass('help-block text-muted small')
                .text(this.translateLabelKey('googleCalendarCreateNewHelp'));

            $nameRow.append($input, $createBtn, $help);
            this.renderNamingPatternHelp($nameRow);
            $wrap.append($checkLabel, $nameRow);
            this.$el.append($wrap);

            $wrap.find('[data-role="create-new-calendar"]').on('change', e => {
                this.createNewCalendar = !!e.currentTarget.checked;

                if (this.createNewCalendar && !this.newCalendarName) {
                    this.newCalendarName = this.defaultNewCalendarName();
                    $input.val(this.newCalendarName);
                }

                this.syncCreateNewUiState();
            });

            $input.on('input', e => {
                this.newCalendarName = String(e.currentTarget.value || '');
            });

            $createBtn.on('click', () => this.submitCreateCalendar());
        }

        defaultNewCalendarName() {
            const entityType = this.model.entityType || this.entityType || '';
            const entityLabel = this.translate(entityType, 'scopeNames') || entityType || 'Calendar';

            return this.buildNamingPatternPreview(entityLabel);
        }

        /**
         * Mirrors CalendarProvisioner::buildCalendarName — `{prefix}-{label}-{suffix}`,
         * dropping empty parts. Prefix/suffix come from Admin → Integrations → Google and
         * are not editable here.
         */
        buildNamingPatternPreview(label) {
            const parts = [this.calendarNamePrefix, label, this.calendarNameSuffix]
                .map(part => (part || '').trim())
                .filter(part => part !== '');

            return parts.join('-');
        }

        renderNamingPatternHelp($nameRow) {
            $nameRow.find('.google-calendar-naming-pattern-help').remove();

            const $help = $('<p>')
                .addClass('help-block text-muted small google-calendar-naming-pattern-help')
                .text(this.translateLabelKey('googleCalendarNamingPatternHelp'));

            $nameRow.append($help);
        }

        syncCreateNewUiState() {
            const $panel = this.$el.find('.google-calendar-create-new');

            if (!$panel.length) {
                return;
            }

            $panel.find('.google-calendar-create-new-name')
                .toggleClass('hidden', !this.createNewCalendar);

            $panel.find('[data-role="create-new-calendar"]')
                .prop('checked', this.createNewCalendar);

            if (this.createNewCalendar) {
                $panel.find('[data-role="new-calendar-name"]').val(
                    this.newCalendarName || this.defaultNewCalendarName()
                );
            }

            const selectize = this.getSelectize();
            const $select = this.$el.find('select');

            if (selectize) {
                if (this.createNewCalendar) {
                    selectize.disable();
                } else {
                    selectize.enable();
                }
            } else if ($select.length) {
                $select.prop('disabled', this.createNewCalendar);
            }
        }

        submitCreateCalendar() {
            if (this.createInProgress) {
                return;
            }

            const summary = String(this.newCalendarName || this.$el.find('[data-role="new-calendar-name"]').val() || '')
                .trim();

            if (!summary) {
                Espo.Ui.error(this.translateLabelKey('googleCalendarCreateNewNameRequired'));

                return;
            }

            this.createInProgress = true;
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            Espo.Ajax.postRequest('GoogleIntegration/calendar/google-calendars', {
                summary: summary,
            }).then(response => {
                this.createInProgress = false;
                Espo.Ui.notify(false);

                const id = String(response.id || '');
                const name = String(response.summary || summary);

                if (!id) {
                    Espo.Ui.error(this.translateLabelKey('googleCalendarCreateNewFailed'));

                    return;
                }

                this.calendarOptionList = this.calendarOptionList.filter(item => item.id !== id);
                this.calendarOptionList.unshift({id: id, summary: name});
                this.calendarLoaded = true;
                this.createNewCalendar = false;
                this.newCalendarName = '';
                this.model.set(this.name, id);
                this.applyCalendarOptions();
                this.renderCreateNewUi();
                this.syncCreateNewUiState();

                Espo.Ui.success(
                    response.created
                        ? this.translateLabelKey('googleCalendarCreateNewSuccess')
                        : this.translateLabelKey('googleCalendarCreateNewExists')
                );
            }).catch(() => {
                this.createInProgress = false;
                Espo.Ui.notify(false);
                Espo.Ui.error(this.translateLabelKey('googleCalendarCreateNewFailed'));
            });
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
                this.syncCreateNewUiState();

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

                    if (typeof data.namePrefix === 'string') {
                        this.calendarNamePrefix = data.namePrefix;
                    }

                    if (typeof data.nameSuffix === 'string') {
                        this.calendarNameSuffix = data.nameSuffix;
                    }

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
