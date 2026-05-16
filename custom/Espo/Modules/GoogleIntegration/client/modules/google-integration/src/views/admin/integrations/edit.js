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
            this.syncCredentialFieldsVisibility();

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

        syncEnabledFromView() {
            const enabledView = this.getFieldView('enabled');

            if (!enabledView || enabledView.readOnly) {
                return;
            }

            const checkbox = this.$el.find('.field[data-name="enabled"] input[type="checkbox"]');
            if (checkbox.length) {
                this.model.set('enabled', !!checkbox.prop('checked'));
                return;
            }

            try {
                enabledView.fetchToModel();
            } catch (error) {
                void error;
            }
        }

        save() {
            this.syncEnabledFromView();

            const requireCredentials = !!this.model.get('enabled');
            const fieldsToSave = requireCredentials ? this.fieldList : this.fieldList.filter(field => field === 'enabled');

            fieldsToSave.forEach(field => {
                const view = this.getFieldView(field);

                if (!this.isFieldViewFetchable(view)) {
                    return;
                }

                try {
                    view.fetchToModel();
                } catch (error) {
                    void error;
                }
            });

            let notValid = false;

            fieldsToSave.forEach(field => {
                const fieldView = this.getFieldView(field);

                if (!this.isFieldViewFetchable(fieldView)) {
                    return;
                }

                if (!requireCredentials && CREDENTIAL_FIELD_NAMES.includes(field)) {
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
