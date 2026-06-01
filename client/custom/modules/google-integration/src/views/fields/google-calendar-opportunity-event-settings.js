define('google-integration:views/fields/google-calendar-opportunity-event-settings', [
    'exports',
    'views/fields/base',
    'google-integration:lib/google-calendar-variable-panel',
    'google-integration:lib/google-calendar-template-variables',
], function (_exports, _base, VariablePanel, TemplateVariables) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _base = _interopRequireDefault(_base);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const OPPORTUNITY_DATE_TYPE_LIST = ['presentationDate', 'closeDate'];
    const METHOD_LIST = ['popup', 'email'];
    const UNIT_LIST = ['minutes', 'hours', 'days', 'weeks'];
    const REMINDER_MODE_LIST = ['none', 'default', 'custom'];
    const VISIBILITY_LIST = ['default', 'private', 'public'];
    const TRANSPARENCY_LIST = ['opaque', 'transparent'];
    const COLOR_LIST = ['', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11'];
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
    const MAX_REMINDERS = 5;
    const MAX_MINUTES = 40320;

    class GoogleCalendarOpportunityEventSettingsField extends _base.default {
        detailTemplateContent = `
            {{#if itemList.length}}
                <div class="list-group">
                    {{#each itemList}}
                        <div class="list-group-item">
                            <strong>{{dateLabel}}</strong>
                            <div class="small text-muted">
                                {{reminderModeLabel}} · {{transparencyLabel}} · {{visibilityLabel}}{{#if colorLabel}} · {{colorLabel}}{{/if}}
                            </div>
                            {{#if reminderList.length}}
                                <ul class="list-unstyled small margin-top-sm">
                                    {{#each reminderList}}
                                        <li>{{methodLabel}}: {{amount}} {{unitLabel}}</li>
                                    {{/each}}
                                </ul>
                            {{/if}}
                        </div>
                    {{/each}}
                </div>
            {{else}}
                <span class="none-value">{{translate 'None'}}</span>
            {{/if}}
        `;

        editTemplateContent = `
            <div data-role="google-calendar-opportunity-event-settings">
                <div class="text-muted small margin-bottom-sm">{{helpText}}</div>
                {{#each itemList}}
                    <div class="panel panel-default google-calendar-opportunity-date-settings-card" data-date-type="{{sourceDateType}}">
                        <div class="panel-heading">
                            <strong>{{dateLabel}}</strong>
                        </div>
                        <div class="panel-body">
                            <div class="row">
                                <div class="col-sm-12 margin-bottom-sm">
                                    <label class="control-label small">{{calendarTemplateFieldLabel}}</label>
                                    <select class="form-control input-sm" data-role="calendarTemplateId">
                                        <option value="">{{translate 'None'}}</option>
                                        {{#each templateOptionList}}
                                            <option value="{{id}}"{{#if selected}} selected{{/if}}>{{name}}</option>
                                        {{/each}}
                                    </select>
                                    <p class="help-block small text-muted margin-top-sm">{{templateSelectHelp}}</p>
                                </div>
                                <div class="col-sm-6">
                                    <label class="control-label small">{{reminderModeFieldLabel}}</label>
                                    <select class="form-control input-sm" data-role="reminderMode">
                                        {{#each reminderModeOptionList}}
                                            <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                        {{/each}}
                                    </select>
                                </div>
                                <div class="col-sm-6">
                                    <label class="control-label small">{{colorFieldLabel}}</label>
                                    <select class="form-control input-sm" data-role="colorId">
                                        {{#each colorOptionList}}
                                            <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                        {{/each}}
                                    </select>
                                </div>
                            </div>
                            <div class="google-calendar-reminder-settings margin-top-sm" data-role="reminders"{{#unless customReminders}} style="display: none;"{{/unless}}>
                                <div class="list-container">
                                    {{#each reminderList}}
                                        <div class="google-calendar-reminder-row row margin-bottom-sm" data-index="{{@index}}">
                                            <div class="col-sm-3">
                                                <select class="form-control input-sm" data-role="method">
                                                    {{#each methodOptionList}}
                                                        <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                                    {{/each}}
                                                </select>
                                            </div>
                                            <div class="col-sm-3">
                                                <input class="form-control input-sm" type="number" min="0" data-role="amount" value="{{amount}}">
                                            </div>
                                            <div class="col-sm-4">
                                                <select class="form-control input-sm" data-role="unit">
                                                    {{#each unitOptionList}}
                                                        <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                                    {{/each}}
                                                </select>
                                            </div>
                                            <div class="col-sm-2">
                                                <button type="button" class="btn btn-default btn-sm" data-action="removeReminder" data-date-type="{{../sourceDateType}}" data-index="{{@index}}">
                                                    <span class="fas fa-times"></span>
                                                </button>
                                            </div>
                                        </div>
                                    {{/each}}
                                </div>
                                <button type="button" class="btn btn-default btn-sm" data-action="addReminder" data-date-type="{{sourceDateType}}">
                                    {{translate 'Add'}}
                                </button>
                            </div>
                            <div class="row margin-top-sm">
                                <div class="col-sm-6">
                                    <label class="control-label small">{{locationFieldLabel}}</label>
                                    <input class="form-control input-sm" data-role="location" value="{{location}}">
                                    <div class="google-calendar-template-variable-helper" data-role="variable-helper-location"></div>
                                </div>
                                <div class="col-sm-3">
                                    <label class="control-label small">{{visibilityFieldLabel}}</label>
                                    <select class="form-control input-sm" data-role="visibility">
                                        {{#each visibilityOptionList}}
                                            <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                        {{/each}}
                                    </select>
                                </div>
                                <div class="col-sm-3">
                                    <label class="control-label small">{{transparencyFieldLabel}}</label>
                                    <select class="form-control input-sm" data-role="transparency">
                                        {{#each transparencyOptionList}}
                                            <option value="{{value}}"{{#if selected}} selected{{/if}}>{{label}}</option>
                                        {{/each}}
                                    </select>
                                </div>
                            </div>
                            <div class="margin-top-sm">
                                <label class="control-label small">{{templateFieldLabel}}</label>
                                <textarea class="form-control input-sm" rows="3" data-role="descriptionTemplateOverride">{{descriptionTemplateOverride}}</textarea>
                                <div class="google-calendar-template-variable-helper" data-role="variable-helper"></div>
                            </div>
                        </div>
                    </div>
                {{/each}}
            </div>
        `;

        setup() {
            super.setup();
            this.templateList = [];
            this.dateSourceOptionList = [];

            const dateListField = this.getDateListFieldName();

            this.listenTo(this.model, `change:${dateListField}`, () => {
                this.model.set(this.name, this.getSettingsList(), {ui: true});
                this.reRender();
            });

            this.loadTemplateList();
            this.loadDateSourceOptions();
        }

        getDateListFieldName() {
            if (this.getMetadata().get(`entityDefs.${this.model.entityType}.fields.googleCalendarDateSourceList`)) {
                return 'googleCalendarDateSourceList';
            }

            return 'googleCalendarOpportunityDateList';
        }

        data() {
            const itemList = this.getSettingsList();

            return {
                helpText: this.translate('googleCalendarPerDateSettingsHelp', 'labels', this.model.entityType),
                itemList: itemList.map(item => this.prepareItemData(item)),
            };
        }

        afterRender() {
            super.afterRender();

            if (this.mode !== 'edit') {
                return;
            }

            this.$el.find('[data-role="google-calendar-opportunity-event-settings"]').on('change input', () => {
                this.model.set(this.name, this.readSettings(), {ui: true});
            });

            this.$el.find('[data-role="calendarTemplateId"]').on('change', e => {
                const $select = $(e.currentTarget);
                const $card = $select.closest('.google-calendar-opportunity-date-settings-card');
                const sourceDateType = String($card.attr('data-date-type') || '');
                const templateId = String($select.val() || '');

                if (!sourceDateType) {
                    return;
                }

                if (!templateId) {
                    this.clearTemplateSelection(sourceDateType);

                    return;
                }

                this.applyTemplateToCard(sourceDateType, templateId);
            });

            this.$el.find('[data-role="reminderMode"]').on('change', e => {
                $(e.currentTarget).closest('.google-calendar-opportunity-date-settings-card')
                    .find('[data-role="reminders"]')
                    .toggle(e.currentTarget.value === 'custom');
            });

            this.$el.find('[data-action="addReminder"]').on('click', e => {
                this.addReminder(e.currentTarget.dataset.dateType);
            });

            this.$el.find('[data-action="removeReminder"]').on('click', e => {
                this.removeReminder(e.currentTarget.dataset.dateType, Number(e.currentTarget.dataset.index));
            });

            this.renderVariablePickers();
            this.decorateColorSelects();
        }

        fetch() {
            const selected = this.getSelectedDateList();
            const fromDom = this.readSettings();
            const domByType = {};

            fromDom.forEach(row => {
                domByType[row.sourceDateType] = row;
            });

            const list = selected.map(sourceDateType => {
                return domByType[sourceDateType]
                    || this.normalizeItem({sourceDateType});
            });

            return {[this.name]: list};
        }

        validate() {
            if (super.validate()) {
                return true;
            }

            const allowed = this.getAllowedDateTypeList();

            if (!allowed.length && this.model.entityType !== 'Opportunity') {
                return false;
            }

            for (const item of this.readSettings()) {
                if (!allowed.includes(item.sourceDateType)) {
                    this.showValidationMessage('Invalid Google Calendar date settings.');
                    return true;
                }

                if (item.reminders.length > MAX_REMINDERS) {
                    this.showValidationMessage(`Max ${MAX_REMINDERS} reminders.`);
                    return true;
                }

                for (const row of item.reminders) {
                    const minutes = this.toMinutes(row);

                    if (minutes < 0 || minutes > MAX_MINUTES) {
                        this.showValidationMessage('Reminder must be between 0 minutes and 4 weeks.');
                        return true;
                    }
                }
            }

            return false;
        }

        getSettingsList() {
            const currentList = Array.isArray(this.model.get(this.name)) ? this.model.get(this.name) : [];
            const selectedDateList = this.getSelectedDateList();

            return selectedDateList.map(sourceDateType => {
                const existing = currentList.find(item => {
                    if (!item || typeof item !== 'object') {
                        return false;
                    }

                    return item.sourceDateType === sourceDateType;
                }) || {};

                return this.normalizeItem({sourceDateType, ...existing});
            });
        }

        getSelectedDateList() {
            const selected = this.model.get(this.getDateListFieldName());
            const allowed = this.getAllowedDateTypeList();

            if (!Array.isArray(selected) || !selected.length) {
                return allowed.length ? [allowed[0]] : [];
            }

            const filtered = selected.filter(item => allowed.includes(item));

            return filtered.length ? filtered : (allowed.length ? [allowed[0]] : []);
        }

        getAllowedDateTypeList() {
            if (this.dateSourceOptionList.length) {
                return this.dateSourceOptionList.map(item => item.sourceDateType);
            }

            if (this.model.entityType === 'Opportunity') {
                return OPPORTUNITY_DATE_TYPE_LIST;
            }

            return [];
        }

        loadDateSourceOptions() {
            Espo.Ajax.getRequest(`GoogleIntegration/calendar/date-source-options/${this.model.entityType}`)
                .then(data => {
                    this.dateSourceOptionList = Array.isArray(data.sources) ? data.sources : [];

                    if (this.isRendered()) {
                        this.reRender();
                    }
                })
                .catch(() => {
                    this.dateSourceOptionList = [];
                });
        }

        normalizeItem(item) {
            const allowed = this.getAllowedDateTypeList();
            const fallback = allowed[0] || 'closeDate';

            return {
                sourceDateType: allowed.includes(item.sourceDateType) ? item.sourceDateType : fallback,
                reminderMode: REMINDER_MODE_LIST.includes(item.reminderMode)
                    ? item.reminderMode
                    : (this.model.entityType === 'VolunteerEmployee'
                        ? 'none'
                        : (this.model.get('googleCalendarReminderMode') || 'none')),
                reminders: this.normalizeReminders(item.reminders || this.model.get('googleCalendarReminders')),
                location: String(item.location ?? this.model.get('googleCalendarLocation') ?? ''),
                visibility: VISIBILITY_LIST.includes(item.visibility) ? item.visibility : this.model.get('googleCalendarVisibility') || 'default',
                transparency: TRANSPARENCY_LIST.includes(item.transparency) ? item.transparency : this.model.get('googleCalendarTransparency') || 'opaque',
                colorId: COLOR_LIST.includes(String(item.colorId ?? '')) ? String(item.colorId ?? '') : String(this.model.get('googleCalendarColorId') || ''),
                calendarTemplateId: String(item.calendarTemplateId ?? this.model.get('googleCalendarTemplateId') ?? ''),
                descriptionTemplateOverride: String(item.descriptionTemplateOverride ?? this.model.get('googleCalendarDescriptionTemplateOverride') ?? ''),
            };
        }

        normalizeReminders(value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value
                .filter(item => item && typeof item === 'object')
                .slice(0, MAX_REMINDERS)
                .map(item => ({
                    method: METHOD_LIST.includes(item.method) ? item.method : 'popup',
                    amount: Math.max(0, Number.parseInt(item.amount, 10) || 0),
                    unit: UNIT_LIST.includes(item.unit) ? item.unit : 'days',
                }));
        }

        prepareItemData(item) {
            return {
                ...item,
                dateLabel: this.translateDateType(item.sourceDateType),
                helpText: this.translate('googleCalendarPerDateSettingsHelp', 'labels', this.model.entityType),
                reminderModeFieldLabel: this.translate('googleCalendarReminderMode', 'fields', this.model.entityType),
                colorFieldLabel: this.translate('googleCalendarColorId', 'fields', this.model.entityType),
                locationFieldLabel: this.translate('googleCalendarLocation', 'fields', this.model.entityType),
                visibilityFieldLabel: this.translate('googleCalendarVisibility', 'fields', this.model.entityType),
                transparencyFieldLabel: this.translate('googleCalendarTransparency', 'fields', this.model.entityType),
                calendarTemplateFieldLabel: this.translate('googleCalendarTemplate', 'fields', this.model.entityType),
                templateSelectHelp: this.translate('googleCalendarTemplateSelectHelp', 'labels', this.model.entityType),
                templateFieldLabel: this.translate('googleCalendarDescriptionTemplateOverride', 'fields', this.model.entityType),
                reminderModeLabel: this.translateOption(item.reminderMode, 'googleCalendarReminderMode'),
                visibilityLabel: this.translateOption(item.visibility, 'googleCalendarVisibility'),
                transparencyLabel: this.translateOption(item.transparency, 'googleCalendarTransparency'),
                colorLabel: this.translateOption(item.colorId, 'googleCalendarColorId'),
                customReminders: item.reminderMode === 'custom',
                templateOptionList: this.getTemplateOptionList(item.calendarTemplateId),
                reminderModeOptionList: this.getOptionList(REMINDER_MODE_LIST, item.reminderMode, 'googleCalendarReminderMode'),
                visibilityOptionList: this.getOptionList(VISIBILITY_LIST, item.visibility, 'googleCalendarVisibility'),
                transparencyOptionList: this.getOptionList(TRANSPARENCY_LIST, item.transparency, 'googleCalendarTransparency'),
                colorOptionList: this.getOptionList(COLOR_LIST, item.colorId, 'googleCalendarColorId'),
                reminderList: item.reminders.map(row => ({
                    ...row,
                    methodLabel: this.translateOption(row.method, 'googleCalendarReminders'),
                    unitLabel: this.translateOption(row.unit, 'googleCalendarReminders'),
                    methodOptionList: this.getOptionList(METHOD_LIST, row.method, 'googleCalendarReminders'),
                    unitOptionList: this.getOptionList(UNIT_LIST, row.unit, 'googleCalendarReminders'),
                })),
            };
        }

        readSettings() {
            const rows = [];

            this.$el.find('.google-calendar-opportunity-date-settings-card').each((i, element) => {
                const $card = $(element);
                rows.push(this.normalizeItem({
                    sourceDateType: $card.attr('data-date-type'),
                    reminderMode: $card.find('[data-role="reminderMode"]').val(),
                    reminders: this.readReminderRows($card),
                    location: $card.find('[data-role="location"]').val(),
                    visibility: $card.find('[data-role="visibility"]').val(),
                    transparency: $card.find('[data-role="transparency"]').val(),
                    colorId: $card.find('[data-role="colorId"]').val(),
                    calendarTemplateId: $card.find('[data-role="calendarTemplateId"]').val(),
                    descriptionTemplateOverride: $card.find('[data-role="descriptionTemplateOverride"]').val(),
                }));
            });

            return rows;
        }

        readReminderRows($card) {
            const rows = [];

            $card.find('.google-calendar-reminder-row').each((i, element) => {
                const $row = $(element);
                rows.push({
                    method: $row.find('[data-role="method"]').val() || 'popup',
                    amount: Math.max(0, Number.parseInt($row.find('[data-role="amount"]').val(), 10) || 0),
                    unit: $row.find('[data-role="unit"]').val() || 'days',
                });
            });

            return rows.slice(0, MAX_REMINDERS);
        }

        addReminder(sourceDateType) {
            const list = this.readSettings();
            const item = list.find(row => row.sourceDateType === sourceDateType);

            if (!item || item.reminders.length >= MAX_REMINDERS) {
                return;
            }

            item.reminderMode = 'custom';
            item.reminders.push({method: 'popup', amount: 1, unit: 'days'});
            this.model.set(this.name, list, {ui: true});
            this.reRender();
        }

        removeReminder(sourceDateType, index) {
            const list = this.readSettings();
            const item = list.find(row => row.sourceDateType === sourceDateType);

            if (!item) {
                return;
            }

            item.reminders.splice(index, 1);
            this.model.set(this.name, list, {ui: true});
            this.reRender();
        }

        clearTemplateSelection(sourceDateType) {
            const list = this.readSettings().map(item => {
                if (item.sourceDateType !== sourceDateType) {
                    return item;
                }

                return this.normalizeItem({
                    ...item,
                    calendarTemplateId: '',
                });
            });

            this.model.set(this.name, list, {ui: true});
            this.reRender();
        }

        applyTemplateToCard(sourceDateType, templateId) {
            const params = {
                entityType: this.model.entityType,
                sourceDateType,
            };

            if (this.model.id) {
                params.entityId = this.model.id;
            }

            Espo.Ui.notify(' ...');

            Espo.Ajax.getRequest(`GoogleIntegration/calendar/template-form/${templateId}`, params)
                .then(data => {
                    Espo.Ui.notify(false);

                    if (!data || typeof data !== 'object') {
                        return;
                    }

                    const list = this.readSettings();
                    const updated = list.map(item => {
                        if (item.sourceDateType !== sourceDateType) {
                            return item;
                        }

                        return this.normalizeItem({
                            ...item,
                            calendarTemplateId: templateId,
                            descriptionTemplateOverride: String(data.description ?? ''),
                            location: String(data.location ?? ''),
                            colorId: String(data.colorId ?? ''),
                            visibility: data.visibility ?? 'default',
                            transparency: data.transparency ?? 'opaque',
                            reminderMode: data.reminderMode ?? 'none',
                            reminders: this.normalizeReminders(data.reminders),
                        });
                    });

                    this.model.set(this.name, updated, {ui: true});
                    this.reRender();
                })
                .catch(xhr => {
                    Espo.Ui.notify(false);

                    Espo.Ui.error(
                        xhr?.responseJSON?.message
                        || this.translate('googleCalendarTemplateLoadFailed', 'labels', this.model.entityType)
                    );
                });
        }

        renderVariablePickers() {
            const fieldList = this.getInsertableFieldList();

            this.$el.find('[data-role="variable-helper"]').each((i, element) => {
                const $helper = $(element);
                const $textarea = $helper.closest('.google-calendar-opportunity-date-settings-card')
                    .find('[data-role="descriptionTemplateOverride"]');

                this.renderVariablePicker($helper, $textarea, fieldList);
            });

            this.$el.find('[data-role="variable-helper-location"]').each((i, element) => {
                const $helper = $(element);
                const $input = $helper.closest('.google-calendar-opportunity-date-settings-card')
                    .find('[data-role="location"]');

                this.renderVariablePicker($helper, $input, fieldList);
            });
        }

        renderVariablePicker($helper, $textarea, fieldList) {
            $helper.empty().css({
                marginTop: '8px',
                padding: '0',
            });

            $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm')
                .css({
                    borderRadius: '999px',
                    paddingLeft: '14px',
                    paddingRight: '14px',
                })
                .text(this.translate('googleCalendarTemplateVariables', 'labels', 'Global'))
                .on('click', () => {
                    VariablePanel.open({
                        stateKey: `${this.model.entityType}:${this.name}`,
                        anchorEl: $textarea,
                        fieldList,
                        ownerView: this,
                        onSelect: name => this.insertVariable($textarea, name),
                        translate: (key, category, scope) => this.translate(key, category, scope || this.model.entityType),
                        title: this.translate('googleCalendarTemplateVariables', 'labels', 'Global'),
                    });
                })
                .appendTo($helper);
        }


        getInsertableFieldList() {
            return TemplateVariables.buildInsertableFieldList({
                metadata: this.getMetadata(),
                entityType: this.model.entityType,
                translate: (key, category, scope) => this.translate(key, category, scope),
                currentGroupLabel: this.translate('googleCalendarCurrentRecordFields', 'labels', this.model.entityType),
                relatedGroupLabel: this.translate('googleCalendarRelatedRecordFields', 'labels', this.model.entityType),
                hasRelatedLink: (linkName, type) => this.hasActualRelatedRecord(linkName, type),
            });
        }

        hasActualRelatedRecord(linkName, type) {
            if (type === 'linkMultiple') {
                const ids = this.model.get(`${linkName}Ids`);
                return Array.isArray(ids) && ids.length > 0;
            }

            return !!this.model.get(`${linkName}Id`);
        }

        insertVariable($input, name) {
            if (!$input.length || !name) {
                return;
            }

            const element = $input.get(0);
            const value = String($input.val() || '');
            const variable = `{{${name}}}`;
            const start = element.selectionStart ?? value.length;
            const end = element.selectionEnd ?? start;
            const nextValue = value.slice(0, start) + variable + value.slice(end);

            $input.val(nextValue).trigger('input').trigger('change');
            this.model.set(this.name, this.readSettings(), {ui: true});

            if (typeof element.setSelectionRange === 'function') {
                const cursor = start + variable.length;
                element.setSelectionRange(cursor, cursor);
            }

            $input.trigger('focus');
        }

        getOptionList(values, selected, field) {
            return values.map(value => ({
                value,
                label: this.translateOption(value, field),
                selected: value === selected,
            }));
        }

        getTemplateOptionList(selectedId) {
            return (this.templateList || []).map(item => ({
                id: item.id,
                name: item.name,
                selected: item.id === selectedId,
            }));
        }

        loadTemplateList() {
            Espo.Ajax.getRequest(`GoogleIntegration/calendar/template-options/${this.model.entityType}`)
                .then(data => {
                    this.templateList = Array.isArray(data.templates) ? data.templates : [];

                    if (this.isRendered()) {
                        this.reRender();
                    }
                })
                .catch(xhr => {
                    this.templateList = [];

                    if (this.isEditMode()) {
                        Espo.Ui.error(
                            xhr?.responseJSON?.message
                            || this.translate('googleCalendarTemplateLoadFailed', 'labels', this.model.entityType)
                        );
                    }
                });
        }

        translateOption(value, field) {
            return this.getLanguage().translateOption(value, field, this.model.entityType);
        }

        translateDateType(value) {
            const fromSource = (this.dateSourceOptionList || []).find(item => item.sourceDateType === value);

            if (fromSource && fromSource.label) {
                return fromSource.label;
            }

            if (value === 'presentationDate') {
                return this.translate('googleCalendarDatePresentation', 'labels', this.model.entityType);
            }

            if (value === 'closeDate') {
                return this.translate('googleCalendarDateClose', 'labels', this.model.entityType);
            }

            if (value === 'main') {
                return this.translate('googleCalendarDateMain', 'labels', this.model.entityType);
            }

            if (value === 'endDate') {
                return this.translate('googleCalendarDateEnd', 'labels', this.model.entityType);
            }

            return value;
        }

        toMinutes(row) {
            const amount = Number.parseInt(row.amount, 10) || 0;

            if (row.unit === 'weeks') {
                return amount * 7 * 24 * 60;
            }

            if (row.unit === 'days') {
                return amount * 24 * 60;
            }

            if (row.unit === 'hours') {
                return amount * 60;
            }

            return amount;
        }

        decorateColorSelects() {
            this.$el.find('[data-role="colorId"] option').each((i, option) => {
                const $option = $(option);
                const value = String($option.attr('value') || '');

                $option.css({
                    backgroundColor: COLOR_MAP[value] || COLOR_MAP[''],
                    color: ['3', '6', '8', '9', '10', '11'].includes(value) ? '#fff' : '#111',
                });
            });
        }
    }

    _exports.default = GoogleCalendarOpportunityEventSettingsField;
});
