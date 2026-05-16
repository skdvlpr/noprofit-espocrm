define('google-integration:views/external-account/oauth2', ['exports', 'views/external-account/oauth2'], function (_exports, _parent) {
    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _parent = _interopRequireDefault(_parent);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const SYNC_MODE_OPTIONS = ['none', 'bidirectional', 'crmToGoogle', 'googleToCrm'];

    class GoogleIntegrationExternalAccountOauth2View extends _parent.default {
        template = 'google-integration:external-account/oauth2';

        connectInProgress = false;

        data() {
            return {
                ...super.data(),
                showCalendarSyncSettings: this.shouldShowCalendarSyncSettings(),
            };
        }

        setup() {
            super.setup();

            this.listenToOnce(this.model, 'sync', () => {
                this.initCalendarSyncModeField();
            });

            this.listenTo(this.model, 'change:enabled', () => {
                this.reRender();
            });
        }

        setConnected() {
            super.setConnected();
            this.reRender();
        }

        setNotConnected() {
            super.setNotConnected();
            this.reRender();
        }

        /**
         * Stock ExternalAccount oauth2 uses encodeURI() for query params. That leaves "?" inside
         * redirect_uri unescaped, so Google receives redirect_uri=https://host only and token
         * exchange fails with invalid_grant: Malformed auth code. Espo 9.x OAuth UI uses
         * encodeURIComponent — mirror that here (see espo-main.js processWithData).
         */
        popup(options, callback) {
            options.windowName = options.windowName || 'ConnectWithOAuth';
            options.windowOptions = options.windowOptions || 'location=0,status=0,width=800,height=400';

            let path = options.path;
            const params = options.params || {};
            const query = Object.keys(params)
                .filter(name => params[name])
                .map(name => `${encodeURIComponent(name)}=${encodeURIComponent(params[name])}`)
                .join('&');

            path += '?' + query;

            const parseUrl = str => {
                const queryString = str.includes('?') ? str.substring(str.indexOf('?') + 1) : str;
                let code = null;
                let error = null;

                queryString.split('&').forEach(part => {
                    const eq = part.indexOf('=');

                    if (eq < 0) {
                        return;
                    }

                    const name = decodeURIComponent(part.substring(0, eq));
                    const value = decodeURIComponent(part.substring(eq + 1));

                    if (name === 'code') {
                        code = value;
                    }

                    if (name === 'error') {
                        error = value;
                    }
                });

                if (code) {
                    return {code: code};
                }

                if (error) {
                    return {error: error};
                }

                return null;
            };

            const popupWindow = window.open(path, options.windowName, options.windowOptions);

            if (!popupWindow) {
                if (typeof callback === 'function') {
                    callback();
                }

                return null;
            }

            let handled = false;

            const interval = window.setInterval(() => {
                if (popupWindow.closed) {
                    window.clearInterval(interval);

                    return;
                }

                let res = null;

                try {
                    res = parseUrl(popupWindow.location.href.toString());
                } catch (e) {
                    return;
                }

                if (!res || handled) {
                    return;
                }

                handled = true;
                window.clearInterval(interval);
                popupWindow.close();
                callback.call(this, res);
            }, 500);

            return popupWindow;
        }

        connect() {
            if (this.connectInProgress) {
                return;
            }

            this.connectInProgress = true;

            let exchangeStarted = false;

            const resolvedRedirectUri = this.redirectUri
                || (String(this.getConfig().get('siteUrl') || '') + '?entryPoint=oauthCallback');

            this.popup({
                path: this.getMetadata().get(`integrations.${this.integration}.params.endpoint`),
                params: {
                    client_id: this.clientId,
                    redirect_uri: resolvedRedirectUri,
                    scope: this.getMetadata().get(`integrations.${this.integration}.params.scope`),
                    response_type: 'code',
                    access_type: 'offline',
                    approval_prompt: 'force',
                },
            }, response => {
                this.connectInProgress = false;

                if (exchangeStarted) {
                    return;
                }

                if (response.error) {
                    Espo.Ui.notify(false);

                    return;
                }

                if (!response.code) {
                    Espo.Ui.error(this.translate('Error occurred'));

                    return;
                }

                exchangeStarted = true;
                this.$el.find('[data-action="connect"]').addClass('disabled');

                Espo.Ajax.postRequest('ExternalAccount/action/authorizationCode', {
                    id: this.id,
                    code: response.code,
                    redirectUri: resolvedRedirectUri,
                }).then(response => {
                    Espo.Ui.notify(false);

                    if (response === true) {
                        this.model.fetch().then(() => {
                            this.setConnected();
                            this.initCalendarSyncModeField();
                        });
                    } else {
                        this.setNotConnected();
                    }

                    this.$el.find('[data-action="connect"]').removeClass('disabled');
                }).catch(() => {
                    this.$el.find('[data-action="connect"]').removeClass('disabled');
                });
            });
        }

        shouldShowCalendarSyncSettings() {
            if (this.integration !== 'GoogleIntegration') {
                return false;
            }

            const integrations = this.getConfig().get('integrations') || {};

            if (!integrations.GoogleIntegration) {
                return false;
            }

            return this.isConnected && !!this.model.get('enabled');
        }

        initCalendarSyncModeField() {
            if (!this.shouldShowCalendarSyncSettings()) {
                return;
            }

            const fields = {
                ...(this.model.defs?.fields || {}),
                calendarSyncMode: {
                    type: 'enum',
                    options: SYNC_MODE_OPTIONS,
                    default: 'none',
                },
            };

            this.model.setDefs({fields: fields});

            if (!this.model.get('calendarSyncMode')) {
                this.model.set('calendarSyncMode', 'none', {silent: true});
            }

            if (this.getView('calendarSyncMode')) {
                return;
            }

            this.createView('calendarSyncMode', 'views/fields/enum', {
                model: this.model,
                selector: '.field[data-name="calendarSyncMode"]',
                defs: {
                    name: 'calendarSyncMode',
                    params: {
                        options: SYNC_MODE_OPTIONS,
                        translationHash: this.getSyncModeTranslationHash(),
                    },
                },
                mode: 'edit',
            });

            if (!this.fieldList.includes('calendarSyncMode')) {
                this.fieldList.push('calendarSyncMode');
            }
        }

        getSyncModeTranslationHash() {
            const hash = {};
            const optionsMap = this.getLanguage().translate('calendarSyncMode', 'options', 'ExternalAccount');

            SYNC_MODE_OPTIONS.forEach(option => {
                hash[option] = (typeof optionsMap === 'object' && optionsMap[option]) || option;
            });

            return hash;
        }

        getFieldsForSave() {
            return this.fieldList.filter(field => {
                if (field === 'calendarSyncMode' && !this.shouldShowCalendarSyncSettings()) {
                    return false;
                }

                return true;
            });
        }

        save() {
            if (!this.model.get('enabled')) {
                this.model.unset('calendarSyncMode', {silent: true});
            }

            const fieldsToSave = this.getFieldsForSave();

            fieldsToSave.forEach(field => {
                const view = /** @type {import('views/fields/base').default} */this.getView(field);

                if (!view || view.readOnly) {
                    return;
                }

                view.fetchToModel();
            });

            let notValid = false;

            fieldsToSave.forEach(field => {
                const view = /** @type {import('views/fields/base').default} */this.getView(field);

                if (!view) {
                    return;
                }

                notValid = view.validate() || notValid;
            });

            if (notValid) {
                Espo.Ui.error(this.translate('Not valid'));

                return;
            }

            this.listenToOnce(this.model, 'sync', () => {
                Espo.Ui.success(this.translate('Saved'));

                if (!this.model.get('enabled')) {
                    this.setNotConnected();
                }
            });

            Espo.Ui.notify(this.translate('saving', 'messages'));
            this.model.save();
        }
    }

    _exports.default = GoogleIntegrationExternalAccountOauth2View;
});
