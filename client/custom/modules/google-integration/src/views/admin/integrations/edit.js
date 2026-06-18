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
    const STYLESHEET_ID = 'google-integration-admin-integrations-edit-css';
    const STYLESHEET_PATH = 'client/custom/modules/google-integration/res/css/admin-integrations-edit.css';

    class GoogleIntegrationAdminEditView extends _parent.default {
        template = 'google-integration:admin/integrations/edit';

        setup() {
            this.ensureStylesheet();

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
                this.createFieldView('bool', 'enabled');
                Object.keys(fields).forEach(name => {
                    if (name.startsWith('googleCalendarDescriptionTemplate')) {
                        return;
                    }

                    this.createFieldView(fields[name].type, name, undefined, fields[name]);
                });
            })());
        }

        ensureStylesheet() {
            if (document.getElementById(STYLESHEET_ID)) {
                return;
            }

            const link = document.createElement('link');

            link.id = STYLESHEET_ID;
            link.rel = 'stylesheet';
            link.href = this.getBasePath() + STYLESHEET_PATH;
            document.head.appendChild(link);
        }

        getCalendarNavItems() {
            const items = [
                {
                    href: '#CalendarDateSource',
                    scope: 'CalendarDateSource',
                    modifier: 'date-sources',
                    iconClass: this.getMetadata().get('scopes.CalendarDateSource.iconClass') || 'fas fa-calendar-day',
                    descriptionKey: 'googleCalendarAdminDateSourcesHelp',
                },
                {
                    href: '#CalendarTemplate',
                    scope: 'CalendarTemplate',
                    modifier: 'templates',
                    iconClass: this.getMetadata().get('scopes.CalendarTemplate.iconClass') || 'fas fa-calendar-check',
                    descriptionKey: 'googleCalendarAdminCalendarTemplatesHelp',
                },
            ];

            return items.map(item => ({
                href: item.href,
                modifier: item.modifier,
                iconClass: item.iconClass,
                title: this.translate(item.scope, 'scopeNamesPlural'),
                description: this.translate(item.descriptionKey, 'labels', 'Integration'),
            }));
        }

        data() {
            return {
                integration: this.integration,
                fieldDataList: this.fieldDataList,
                helpText: this.helpText,
                redirectUri: String(this.getConfig().get('siteUrl') || '') + '?entryPoint=oauthCallback',
                calendarConfigTitle: this.translate('googleCalendarAdminConfigTitle', 'labels', 'Integration'),
                calendarConfigHelp: this.translate('googleCalendarAdminConfigHelp', 'labels', 'Integration'),
                calendarNavItems: this.getCalendarNavItems(),
            };
        }

        afterRender() {
            super.afterRender();
            this.syncCredentialFieldsVisibility();
            this.syncCalendarConfigPanelsVisibility();
            this.syncAutoDedicatedWarning();

            this.listenTo(this.model, 'change:enabled', () => {
                this.syncCredentialFieldsVisibility();
                this.syncCalendarConfigPanelsVisibility();
                this.syncAutoDedicatedWarning();
            });

            this.listenTo(this.model, 'change:googleCalendarAutoCreateEnabled', () => {
                this.syncAutoDedicatedWarning();
            });
        }

        syncAutoDedicatedWarning() {
            this.removeAutoDedicatedWarning();

            if (!this.model.get('enabled') || !this.model.get('googleCalendarAutoCreateEnabled')) {
                return;
            }

            Espo.Ajax.getRequest('CalendarDateSource', {
                select: 'calendarRoutingMode,isActive',
                maxSize: 200,
            })
                .then(response => {
                    const list = Array.isArray(response.list) ? response.list : [];
                    const dedicatedCount = list.filter(row => {
                        return !!row.isActive && row.calendarRoutingMode === 'auto_dedicated';
                    }).length;

                    if (dedicatedCount > 0) {
                        return;
                    }

                    this.renderAutoDedicatedWarning();
                })
                .catch(() => {
                    void 0;
                });
        }

        renderAutoDedicatedWarning() {
            if (!this.$el) {
                return;
            }

            const text = this.translate('googleCalendarAutoCreateNoDedicatedWarning', 'labels', 'Integration');
            const linkLabel = this.translate('CalendarDateSource', 'scopeNamesPlural', 'Global');
            const $warning = $('<div>')
                .addClass('alert alert-warning google-calendar-auto-dedicated-warning margin-top')
                .append(
                    $('<span>').addClass('google-calendar-auto-dedicated-warning-text').text(text),
                    ' ',
                    $('<a>')
                        .attr('href', '#CalendarDateSource')
                        .text(linkLabel)
                );

            const $anchor = this.$el.find('.field[data-name="googleCalendarAutoCreateEnabled"]')
                .closest('.cell, .field');

            if ($anchor.length) {
                $anchor.after($warning);
                return;
            }

            this.$el.find('.gi-panel .panel-body-form').first().append($warning);
        }

        removeAutoDedicatedWarning() {
            if (!this.$el) {
                return;
            }

            this.$el.find('.google-calendar-auto-dedicated-warning').remove();
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
