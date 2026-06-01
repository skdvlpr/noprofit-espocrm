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
    const buildTemplateFieldName = entityType => `googleCalendarDescriptionTemplate${entityType}`;

    class GoogleIntegrationAdminEditView extends _parent.default {
        template = 'google-integration:admin/integrations/edit';

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
            this.templateButtonList = [];
            this.templateFieldNameSet = new Set();

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

            this.wait(
                Espo.Ajax.getRequest('GoogleIntegration/calendar/allowed-entity-types')
                    .then(data => {
                        const list = Array.isArray(data.list) ? data.list : [];

                        list.forEach(item => {
                            const fieldName = buildTemplateFieldName(item.entityType);

                            this.templateFieldNameSet.add(fieldName);

                            if (!fieldDefs[fieldName]) {
                                fieldDefs[fieldName] = {type: 'text'};
                            }

                            this.templateButtonList.push({
                                entityType: item.entityType,
                                entityLabel: item.label || item.entityType,
                                fieldName: fieldName,
                            });
                        });
                    })
                    .catch(() => {})
            );

            Object.keys(fields).forEach(name => {
                const defs = {...fields[name]};
                fieldDefs[name] = defs;
                this.fieldList.push(name);

                if (name.startsWith('googleCalendarDescriptionTemplate')) {
                    return;
                }

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
                this.refreshTemplateButtonStates();
                this.createFieldView('bool', 'enabled');
                Object.keys(fields).forEach(name => {
                    if (this.templateFieldNameSet.has(name)) {
                        return;
                    }

                    this.createFieldView(fields[name].type, name, undefined, fields[name]);
                });
            })());
        }

        data() {
            return {
                integration: this.integration,
                fieldDataList: this.fieldDataList,
                templateButtonList: this.templateButtonList,
                templatesTitle: this.translate('googleCalendarAdminTemplatesTitle', 'labels', 'Integration'),
                templatesHelp: this.translate('googleCalendarAdminTemplatesHelp', 'labels', 'Integration'),
                helpText: this.helpText,
                redirectUri: String(this.getConfig().get('siteUrl') || '') + '?entryPoint=oauthCallback',
                dateSourcesTitle: this.translate('googleCalendarAdminDateSourcesTitle', 'labels', 'Integration'),
                dateSourcesHelp: this.translate('googleCalendarAdminDateSourcesHelp', 'labels', 'Integration'),
                calendarTemplatesTitle: this.translate('googleCalendarAdminCalendarTemplatesTitle', 'labels', 'Integration'),
                calendarTemplatesHelp: this.translate('googleCalendarAdminCalendarTemplatesHelp', 'labels', 'Integration'),
            };
        }

        afterRender() {
            super.afterRender();
            this.syncCredentialFieldsVisibility();
            this.bindTemplateButtons();
            this.setupEmbeddedCalendarConfigLists();
            this.syncCalendarConfigPanelsVisibility();

            this.listenTo(this.model, 'change:enabled', () => {
                this.syncCredentialFieldsVisibility();
                this.syncCalendarConfigPanelsVisibility();
            });
        }

        setupEmbeddedCalendarConfigLists() {
            if (this.getView('dateSourcesList')) {
                return;
            }

            const listOptions = {
                headerDisabled: true,
                searchPanel: false,
                checkbox: false,
                massActionsDisabled: true,
            };

            this.createView('dateSourcesList', 'google-integration:views/admin/integrations/embedded-record-list', {
                ...listOptions,
                scope: 'CalendarDateSource',
                el: this.$el.find('[data-role="date-sources-list"]'),
            }, view => {
                view.render();
            });

            this.createView('templatesList', 'google-integration:views/admin/integrations/embedded-record-list', {
                ...listOptions,
                scope: 'CalendarTemplate',
                el: this.$el.find('[data-role="calendar-templates-list"]'),
            }, view => {
                view.render();
            });
        }

        syncCalendarConfigPanelsVisibility() {
            const show = !!this.model.get('enabled');
            const $panels = this.$el.find('.google-calendar-admin-config-panels');

            if (show) {
                $panels.removeClass('hide');
            } else {
                $panels.addClass('hide');
            }
        }

        refreshTemplateButtonStates() {
            this.templateButtonList.forEach(item => {
                const value = this.model.get(item.fieldName);
                item.statusLabel = value
                    ? this.translate('googleCalendarAdminTemplateConfigured', 'labels', 'Integration')
                    : this.translate('googleCalendarAdminTemplateEmpty', 'labels', 'Integration');
            });
        }

        bindTemplateButtons() {
            this.$el.find('[data-action="openTemplateModal"]').on('click', event => {
                const entityType = event.currentTarget.dataset.entityType;
                const fieldName = event.currentTarget.dataset.fieldName;
                const item = this.templateButtonList.find(row => row.entityType === entityType);

                if (!fieldName || !item) {
                    return;
                }

                this.openTemplateModal(item);
            });
        }

        openTemplateModal(item) {
            this.createView('templateModal', 'google-integration:views/admin/integrations/template-modal', {
                entityType: item.entityType,
                entityLabel: item.entityLabel,
                fieldName: item.fieldName,
                value: this.model.get(item.fieldName) || '',
                templateFieldLabel: this.translate(item.fieldName, 'fields', 'Integration'),
            }, view => {
                view.render();

                this.listenToOnce(view, 'apply', data => {
                    this.model.set(data.fieldName, data.value);
                    this.refreshTemplateButtonStates();
                    this.reRender();
                });
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
