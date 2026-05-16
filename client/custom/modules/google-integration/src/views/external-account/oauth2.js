define('google-integration:views/external-account/oauth2', ['exports', 'views/external-account/oauth2'], function (_exports, _parent) {
    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _parent = _interopRequireDefault(_parent);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const SYNC_MODE_OPTIONS = ['none', 'bidirectional', 'crmToGoogle', 'googleToCrm'];
    const OAUTH_MESSAGE_TYPE = 'googleIntegrationOAuthCallback';

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
         * COOP blocks popup.location / popup.closed polling. Authorization code is
         * delivered via postMessage from EntryPoints/OauthCallback (encodeURIComponent
         * for query params — stock encodeURI breaks redirect_uri).
         */
        connect() {
            if (this.connectInProgress) {
                return;
            }

            this.connectInProgress = true;

            let exchangeStarted = false;

            const finishConnect = () => {
                this.connectInProgress = false;
            };

            const handleOAuthResponse = response => {
                if (exchangeStarted) {
                    return;
                }

                exchangeStarted = true;
                window.removeEventListener('message', onMessage);

                if (response.error) {
                    Espo.Ui.notify(false);
                    finishConnect();

                    return;
                }

                if (!response.code || response.code.length < 10) {
                    Espo.Ui.error(this.translate('Error occurred'));
                    finishConnect();

                    return;
                }

                this.$el.find('[data-action="connect"]').addClass('disabled');

                const resolvedRedirectUri = this.redirectUri
                    || (String(this.getConfig().get('siteUrl') || '') + '?entryPoint=oauthCallback');
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
                    finishConnect();
                }).catch(() => {
                    this.$el.find('[data-action="connect"]').removeClass('disabled');
                    finishConnect();
                });
            };

            const onMessage = event => {
                if (event.origin !== window.location.origin) {
                    return;
                }

                const data = event.data;

                if (!data || data.type !== OAUTH_MESSAGE_TYPE) {
                    return;
                }

                handleOAuthResponse(data);
            };

            window.addEventListener('message', onMessage);

            const endpoint = this.getMetadata().get(`integrations.${this.integration}.params.endpoint`);
            const resolvedRedirectUri = this.redirectUri
                || (String(this.getConfig().get('siteUrl') || '') + '?entryPoint=oauthCallback');
            const params = {
                client_id: this.clientId,
                redirect_uri: resolvedRedirectUri,
                scope: this.getMetadata().get(`integrations.${this.integration}.params.scope`),
                response_type: 'code',
                access_type: 'offline',
                approval_prompt: 'force',
            };

            const query = Object.keys(params)
                .filter(name => params[name])
                .map(name => `${encodeURIComponent(name)}=${encodeURIComponent(params[name])}`)
                .join('&');

            const popupWindow = window.open(
                endpoint + '?' + query,
                'ConnectWithOAuth',
                'location=0,status=0,width=800,height=400'
            );

            if (!popupWindow) {
                window.removeEventListener('message', onMessage);
                finishConnect();

                return;
            }

            const waitCloseInterval = window.setInterval(() => {
                if (!popupWindow.closed) {
                    return;
                }

                window.clearInterval(waitCloseInterval);

                if (!exchangeStarted) {
                    window.removeEventListener('message', onMessage);
                    finishConnect();
                }
            }, 500);
        }

        shouldShowCalendarSyncSettings() {
            if (this.integration !== 'GoogleCalendarDrive') {
                return false;
            }

            const integrations = this.getConfig().get('integrations') || {};

            if (!integrations.GoogleCalendarDrive) {
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
                const view = this.getView(field);

                if (!view || view.readOnly) {
                    return;
                }

                view.fetchToModel();
            });

            let notValid = false;

            fieldsToSave.forEach(field => {
                const view = this.getView(field);

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
