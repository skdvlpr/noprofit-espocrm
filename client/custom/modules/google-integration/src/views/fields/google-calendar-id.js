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

        translateLabelKey(key, fallback) {
            const translated = this.translate(key, 'labels', 'Global');

            if (!translated || translated === key) {
                return fallback || key;
            }

            return translated;
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

            const $nameLabel = $('<label>')
                .addClass('control-label')
                .text(this.translateLabelKey(
                    'googleCalendarCreateNewNameLabel',
                    'Calendar name'
                ));

            const $affixRow = $('<div>')
                .addClass('google-calendar-name-affix');

            // Always show locked prefix (default CRM) so naming is visible without admin jargon.
            const prefix = String(this.calendarNamePrefix || '').trim() || 'CRM';
            const suffix = String(this.calendarNameSuffix || '').trim();

            $affixRow.append(
                $('<span>')
                    .addClass('google-calendar-name-affix-fixed')
                    .attr('title', this.translateLabelKey(
                        'googleCalendarNameAffixLocked',
                        'Set automatically'
                    ))
                    .text(prefix)
            );

            const middleName = this.stripAffixes(this.newCalendarName || this.defaultNewCalendarName());
            this.newCalendarName = middleName;

            const $input = $('<input>')
                .addClass('form-control google-calendar-name-affix-middle')
                .attr({
                    type: 'text',
                    maxlength: 200,
                    placeholder: this.translateLabelKey(
                        'googleCalendarCreateNewNamePlaceholder',
                        'Name'
                    ),
                    'data-role': 'new-calendar-name',
                    'aria-label': this.translateLabelKey(
                        'googleCalendarCreateNewNameLabel',
                        'Calendar name'
                    ),
                })
                .val(middleName);

            $affixRow.append($input);

            if (suffix) {
                $affixRow.append(
                    $('<span>')
                        .addClass('google-calendar-name-affix-fixed')
                        .attr('title', this.translateLabelKey(
                            'googleCalendarNameAffixLocked',
                            'Set automatically'
                        ))
                        .text(suffix)
                );
            }

            const $preview = $('<p>')
                .addClass('help-block text-muted small google-calendar-naming-preview')
                .attr('data-role', 'new-calendar-preview');

            const $createBtn = $('<button>')
                .attr({type: 'button'})
                .addClass('btn btn-default btn-sm margin-top')
                .attr('data-role', 'create-calendar-submit')
                .text(this.translateLabelKey('googleCalendarCreateNewSubmit', 'Create calendar'));

            $nameRow.append($nameLabel, $affixRow, $preview, $createBtn);
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
                this.newCalendarName = this.stripAffixes(String(e.currentTarget.value || ''));
                e.currentTarget.value = this.newCalendarName;
                this.updateNamingPreview();
            });

            $createBtn.on('click', () => this.submitCreateCalendar());
            this.updateNamingPreview();
        }

        /** Middle label only — prefix/suffix are admin-locked affixes. */
        defaultNewCalendarName() {
            const entityType = this.model.entityType || this.entityType || '';

            return this.translate(entityType, 'scopeNames') || entityType || 'Calendar';
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

        stripAffixes(name) {
            let value = String(name || '').trim();
            const prefix = String(this.calendarNamePrefix || '').trim();
            const suffix = String(this.calendarNameSuffix || '').trim();

            if (prefix) {
                if (value.indexOf(prefix + ' - ') === 0) {
                    value = value.slice(prefix.length + 3).trim();
                } else if (value.indexOf(prefix + '-') === 0) {
                    value = value.slice(prefix.length + 1).trim();
                }
            }

            if (suffix) {
                if (value.length >= suffix.length + 3 && value.slice(-(suffix.length + 3)) === ' - ' + suffix) {
                    value = value.slice(0, -(suffix.length + 3)).trim();
                } else if (value.length >= suffix.length + 1 && value.slice(-(suffix.length + 1)) === '-' + suffix) {
                    value = value.slice(0, -(suffix.length + 1)).trim();
                }
            }

            return value;
        }

        updateNamingPreview() {
            const $preview = this.$el.find('[data-role="new-calendar-preview"]');

            if (!$preview.length) {
                return;
            }

            const label = this.stripAffixes(
                this.newCalendarName || this.$el.find('[data-role="new-calendar-name"]').val() || ''
            );
            const full = this.buildNamingPatternPreview(label || this.defaultNewCalendarName());
            const template = this.translateLabelKey(
                'googleCalendarNamingPatternHelp',
                'Will be created as: {name}'
            );

            $preview.text(String(template).replace(/\{name\}/g, full));
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
                const middle = this.stripAffixes(this.newCalendarName || this.defaultNewCalendarName());
                this.newCalendarName = middle;
                $panel.find('[data-role="new-calendar-name"]').val(middle);
                this.updateNamingPreview();
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

            const label = this.stripAffixes(
                String(this.newCalendarName || this.$el.find('[data-role="new-calendar-name"]').val() || '')
            );

            if (!label) {
                Espo.Ui.error(this.translateLabelKey('googleCalendarCreateNewNameRequired'));

                return;
            }

            this.createInProgress = true;
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            Espo.Ajax.postRequest('GoogleIntegration/calendar/google-calendars', {
                label: label,
            }).then(response => {
                this.createInProgress = false;
                Espo.Ui.notify(false);

                const id = String(response.id || '');
                const name = String(response.summary || this.buildNamingPatternPreview(label));

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

                    if (this.isRendered() && this.mode === 'edit') {
                        this.renderCreateNewUi();
                        this.syncCreateNewUiState();
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
