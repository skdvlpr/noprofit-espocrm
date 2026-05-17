define('google-integration:views/admin/integrations/edit', ['exports', 'views/admin/integrations/edit', 'model'], function (_exports, _parent, _model) {
    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _parent = _interopRequireDefault(_parent);
    _model = _interopRequireDefault(_model);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const CREDENTIAL_FIELD_DEFS = {
        clientId: {
            type: 'varchar',
            maxLength: 255,
            required: true,
        },
        clientSecret: {
            type: 'password',
            maxLength: 255,
            required: true,
        },
    };

    const CREDENTIAL_FIELD_NAMES = Object.keys(CREDENTIAL_FIELD_DEFS);
    const TEMPLATE_FIELD_ENTITY_MAP = {
        googleCalendarDescriptionTemplateMeeting: 'Meeting',
        googleCalendarDescriptionTemplateCall: 'Call',
        googleCalendarDescriptionTemplateTask: 'Task',
        googleCalendarDescriptionTemplateOpportunity: 'Opportunity',
    };

    class GoogleIntegrationAdminEditView extends _parent.default {
        template = 'admin/integrations/oauth2';

        setup() {
            this.addActionHandler('save', () => this.save());
            this.addActionHandler('cancel', () => this.actionCancel());

            this.integration = this.options.integration;
            this.helpText = null;

            if (this.getLanguage().has(this.integration, 'help', 'Integration')) {
                this.helpText = this.translate(this.integration, 'help', 'Integration');
            }

            this.fieldList = [];
            this.fieldDataList = [];

            this.model = new _model.default({}, {
                entityType: 'Integration',
                urlRoot: 'Integration',
            });
            this.model.id = this.integration;

            const fieldDefs = {
                enabled: {
                    required: true,
                    type: 'bool',
                },
            };

            const metaFields = this.getMetadata().get(`integrations.${this.integration}.fields`) || {};
            const fields = {...CREDENTIAL_FIELD_DEFS, ...metaFields};

            Object.keys(fields).forEach(name => {
                const defs = {...fields[name]};
                fieldDefs[name] = defs;

                let label = this.translate(name, 'fields', 'Integration');

                if (defs.labelTranslation) {
                    label = this.getLanguage().translatePath(defs.labelTranslation);
                }

                this.fieldDataList.push({
                    name: name,
                    label: label,
                });
            });

            this.model.setDefs({fields: fieldDefs});
            this.model.populateDefaults();

            this.wait((async () => {
                await this.model.fetch();
                this.createFieldView('bool', 'enabled');
                Object.keys(fields).forEach(name => {
                    this.createFieldView(fields[name].type, name, undefined, fields[name]);
                });
            })());
        }

        data() {
            return {
                integration: this.integration,
                fieldDataList: this.fieldDataList,
                helpText: this.helpText,
                redirectUri: String(this.getConfig().get('siteUrl') || '') + '?entryPoint=oauthCallback',
            };
        }

        afterRender() {
            super.afterRender();
            this.syncCredentialFieldsVisibility();
            this.renderTemplateVariableHelpers();

            this.listenTo(this.model, 'change:enabled', () => {
                this.syncCredentialFieldsVisibility();
            });
        }

        syncCredentialFieldsVisibility() {
            const show = !!this.model.get('enabled');

            CREDENTIAL_FIELD_NAMES.forEach(name => {
                if (show) {
                    this.showField(name);
                } else {
                    this.hideField(name);
                }
            });
        }

        renderTemplateVariableHelpers() {
            Object.keys(TEMPLATE_FIELD_ENTITY_MAP).forEach(field => {
                this.renderTemplateVariableHelper(field, TEMPLATE_FIELD_ENTITY_MAP[field]);
            });
        }

        renderTemplateVariableHelper(field, entityType) {
            const $field = this.$el.find(`.field[data-name="${field}"]`).last();

            if (!$field.length || $field.find('.google-calendar-template-variable-helper').length) {
                return;
            }

            const fieldList = this.getInsertableFieldList(entityType);

            if (!fieldList.length) {
                return;
            }

            const $select = $('<select>')
                .addClass('form-control input-sm')
                .attr('data-role', 'google-calendar-template-variable');

            fieldList.forEach(item => {
                $('<option>')
                    .attr('value', item.name)
                    .text(item.label)
                    .appendTo($select);
            });

            const $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm')
                .text(this.translate('Insert'))
                .on('click', () => this.insertVariable(field, String($select.val() || '')));

            const $helper = $('<div>')
                .addClass('google-calendar-template-variable-helper input-group input-group-sm margin-top');

            $('<span>')
                .addClass('input-group-addon')
                .text(this.translate('googleCalendarTemplateVariables', 'labels', 'Integration'))
                .appendTo($helper);

            $select.appendTo($helper);

            $('<span>')
                .addClass('input-group-btn')
                .append($button)
                .appendTo($helper);

            $field.append($helper);
        }

        getInsertableFieldList(entityType) {
            const fields = this.getMetadata().get(`entityDefs.${entityType}.fields`) || {};

            return Object.keys(fields)
                .filter(name => !name.startsWith('googleCalendar') && !fields[name].utility)
                .map(name => ({
                    name,
                    label: this.translate(name, 'fields', entityType),
                }))
                .sort((a, b) => a.label.localeCompare(b.label));
        }

        insertVariable(field, name) {
            if (!name) {
                return;
            }

            const $input = this.$el
                .find(`.field[data-name="${field}"] textarea, .field[data-name="${field}"] input`)
                .first();

            if (!$input.length) {
                return;
            }

            const element = $input.get(0);
            const value = $input.val() || '';
            const variable = `{{${name}}}`;
            const start = element.selectionStart ?? String(value).length;
            const end = element.selectionEnd ?? start;
            const nextValue = String(value).slice(0, start) + variable + String(value).slice(end);

            $input.val(nextValue).trigger('change');

            if (typeof element.setSelectionRange === 'function') {
                const cursor = start + variable.length;
                element.setSelectionRange(cursor, cursor);
            }
        }

        isFieldViewFetchable(view) {
            if (!view || view.readOnly || view.disabled) {
                return false;
            }

            const el = view.$el;

            if (!el || !el.length) {
                return false;
            }

            if (el.closest('.hide').length > 0) {
                return false;
            }

            const inputEl = typeof view.get$element === 'function'
                ? view.get$element()
                : (view.$element || null);

            if (!inputEl || !inputEl.length) {
                return false;
            }

            return true;
        }

        /**
         * Read Abilitato from the checkbox before deciding which fields to fetch.
         * getFieldsForSave() must not use stale model.enabled (user may have just unchecked).
         */
        syncEnabledFromView() {
            const enabledView = this.getFieldView('enabled');

            if (!enabledView || enabledView.readOnly) {
                return;
            }

            const checkbox = this.$el.find('.field[data-name="enabled"] input[type="checkbox"]');
            if (checkbox.length) {
                const enabledValue = !!checkbox.prop('checked');
                this.model.set('enabled', enabledValue);
                return;
            }

            const el = enabledView.$el;
            if (!el || !el.length) {
                return;
            }

            try {
                enabledView.fetchToModel();
            } catch (error) {
                void error;
            }
        }

        getFieldsForSave() {
            const requireCredentials = !!this.model.get('enabled');

            if (requireCredentials) {
                return this.fieldList;
            }

            return this.fieldList.filter(field => field === 'enabled');
        }

        save() {
            this.syncEnabledFromView();

            const fieldsToSave = this.getFieldsForSave().filter(field => field !== 'enabled');
            fieldsToSave.forEach(field => {
                const view = this.getFieldView(field);
                const isFetchable = this.isFieldViewFetchable(view);

                if (!isFetchable) {
                    return;
                }

                try {
                    view.fetchToModel();
                } catch (error) {
                    void error;
                }
            });

            let notValid = false;

            const enabledView = this.getFieldView('enabled');

            if (this.isFieldViewFetchable(enabledView)) {
                notValid = enabledView.validate() || notValid;
            }

            fieldsToSave.forEach(field => {
                const fieldView = this.getFieldView(field);

                if (!this.isFieldViewFetchable(fieldView)) {
                    return;
                }

                notValid = fieldView.validate() || notValid;
            });

            if (notValid) {
                Espo.Ui.error(this.translate('Not valid'));

                return;
            }

            this.listenToOnce(this.model, 'sync', () => {
                Espo.Ui.success(this.translate('Saved'));
            });

            Espo.Ui.notify(this.translate('saving', 'messages'));
            this.model.save();
        }
    }

    _exports.default = GoogleIntegrationAdminEditView;
});
