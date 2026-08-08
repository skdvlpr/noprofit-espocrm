define('google-integration:views/external-account/oauth2', ['exports', 'views/external-account/oauth2'], function (_exports, _parent) {
    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _parent = _interopRequireDefault(_parent);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    const OAUTH_MESSAGE_TYPE = 'googleIntegrationOAuthCallback';
    const OAUTH_STATE_STORAGE_KEY = 'googleIntegrationOAuthState';
    const OAUTH_SAFETY_ACK_KEY = 'googleIntegrationOAuthSafetyAck';

    const ROUTING_OPTIONS = ['primary', 'user_pick', 'auto_dedicated'];

    class GoogleIntegrationExternalAccountOauth2View extends _parent.default {
        template = 'google-integration:external-account/oauth2';

        connectInProgress = false;
        oauthSafetyAck = false;
        calendarFieldsReady = false;

        data() {
            const connected = !!this.isConnected;

            return {
                ...super.data(),
                showGoogleAccountProfile: this.shouldShowGoogleAccountProfile(),
                googleAccountEmail: this.model.get('googleAccountEmail'),
                googleAccountName: this.model.get('googleAccountName') || this.model.get('googleAccountEmail'),
                googleAccountPicture: this.model.get('googleAccountPicture'),
                googleAccountProfileMissing: connected && !this.model.get('googleAccountEmail'),
                oauthSafetyBody: this.translate('googleConnectWarningText', 'labels', 'ExternalAccount'),
                oauthSafetyAck: this.oauthSafetyAck,
                oauthSafetyAckLocked: connected,
                showCalendarSettings: connected && this.model.get('enabled'),
            };
        }

        setup() {
            super.setup();

            this.addActionHandler('disconnect', () => this.actionDisconnect());

            this.listenTo(this.model, 'change:enabled', () => {
                this.reRender();
            });

            this.listenToOnce(this.model, 'sync', () => {
                this.initOauthSafetyAck();
                this.ensureCalendarUserFields();
            });
        }

        afterRender() {
            super.afterRender();

            this.$el.find('[data-name="oauthSafetyAck"]').off('change.oauthSafety')
                .on('change.oauthSafety', e => {
                    if (this.isConnected) {
                        e.preventDefault();
                        this.$el.find('[data-name="oauthSafetyAck"]').prop('checked', true);

                        return;
                    }

                    this.oauthSafetyAck = !!e.currentTarget.checked;
                    this.persistOauthSafetyAck(this.oauthSafetyAck);
                    this.syncConnectButtons();
                });

            this.syncConnectButtons();
        }

        initOauthSafetyAck() {
            if (this.isConnected) {
                this.oauthSafetyAck = true;
                this.persistOauthSafetyAck(true);

                return;
            }

            this.oauthSafetyAck = this.readOauthSafetyAck();
        }

        readOauthSafetyAck() {
            try {
                return sessionStorage.getItem(this.oauthSafetyStorageKey()) === '1';
            } catch (e) {
                return false;
            }
        }

        persistOauthSafetyAck(value) {
            try {
                if (value) {
                    sessionStorage.setItem(this.oauthSafetyStorageKey(), '1');
                } else {
                    sessionStorage.removeItem(this.oauthSafetyStorageKey());
                }
            } catch (e) {
                // ignore
            }
        }

        oauthSafetyStorageKey() {
            return OAUTH_SAFETY_ACK_KEY + ':' + String(this.id || '');
        }

        syncConnectButtons() {
            const allow = !!this.oauthSafetyAck;
            const $buttons = this.$el.find('[data-action="connect"]');

            $buttons.prop('disabled', !allow);
            $buttons.toggleClass('disabled', !allow);
        }

        ensureCalendarUserFields() {
            if (this.calendarFieldsReady || this.integration !== 'GoogleCalendarDrive') {
                return;
            }

            this.calendarFieldsReady = true;

            const fields = this.model.defs.fields || {};

            fields.calendarRoutingMode = {
                type: 'enum',
                options: ROUTING_OPTIONS,
                default: 'primary',
            };
            fields.overlayCalendarIdList = {
                type: 'multiEnum',
                options: ['primary'],
                translation: 'ExternalAccount.options.overlayCalendarIdList',
            };

            this.model.defs.fields = fields;

            if (!this.model.get('calendarRoutingMode')) {
                this.model.set('calendarRoutingMode', 'primary', {silent: true});
            }

            if (!this.model.has('overlayCalendarIdList') || this.model.get('overlayCalendarIdList') == null) {
                this.model.set('overlayCalendarIdList', ['primary'], {silent: true});
            }

            this.createFieldView('enum', 'calendarRoutingMode', false, {
                options: ROUTING_OPTIONS,
            });

            this.createFieldView('multiEnum', 'overlayCalendarIdList', false, {
                options: ['primary'],
            });

            if (this.isConnected) {
                this.loadOverlayCalendarOptions();
            }
        }

        loadOverlayCalendarOptions() {
            Espo.Ajax.getRequest('GoogleIntegration/calendar/google-calendars', {forOverlay: '1'})
                .then(response => {
                    const list = (response && response.list) ? response.list : [];
                    const options = ['primary'];
                    const translated = {
                        primary: this.translate('primary', 'options', 'ExternalAccount')
                            || this.translate('calendarRoutingMode', 'options', 'ExternalAccount')
                            || 'Primary calendar',
                    };

                    // Prefer human label for primary when Google marks one.
                    list.forEach(row => {
                        if (row && row.primary && row.summary) {
                            translated.primary = String(row.summary) + ' (primary)';
                        }
                    });

                    list.forEach(row => {
                        const id = String((row && row.id) || '');

                        if (!id || id === 'primary' || options.includes(id)) {
                            return;
                        }

                        if (row.isCrmCalendar) {
                            return;
                        }

                        options.push(id);
                        translated[id] = row.summary || id;
                    });

                    const fieldView = this.getView('overlayCalendarIdList');

                    if (fieldView) {
                        fieldView.params = fieldView.params || {};
                        fieldView.params.options = options;
                        fieldView.translatedOptions = translated;

                        if (typeof fieldView.reRender === 'function') {
                            fieldView.reRender();
                        }
                    }
                })
                .catch(() => {
                    // Keep primary-only fallback.
                });
        }

        setConnected() {
            this.oauthSafetyAck = true;
            this.persistOauthSafetyAck(true);
            super.setConnected();
            this.ensureCalendarUserFields();
            this.loadOverlayCalendarOptions();
            this.reRender();
        }

        setNotConnected() {
            this.oauthSafetyAck = false;
            this.persistOauthSafetyAck(false);
            super.setNotConnected();
            this.reRender();
        }

        /**
         * Espo core has no Disconnect button: uncheck Enabled + Save.
         * Explicit action so users can clear tokens without hunting the checkbox.
         */
        actionDisconnect() {
            const enabledView = this.getView('enabled');

            if (enabledView && typeof enabledView.fetchToModel === 'function') {
                this.model.set('enabled', false);

                if (typeof enabledView.reRender === 'function') {
                    enabledView.reRender();
                }
            } else {
                this.model.set('enabled', false);
            }

            this.save();
        }

        shouldShowGoogleAccountProfile() {
            return this.integration === 'GoogleCalendarDrive'
                && this.isConnected
                && !!this.model.get('googleAccountEmail');
        }

        /**
         * COOP blocks popup.location / popup.closed polling. Authorization code is
         * delivered via postMessage from EntryPoints/OauthCallback (encodeURIComponent
         * for query params — stock encodeURI breaks redirect_uri).
         *
         * Manual export only: no background calendarSyncMode UI (forced to none server-side).
         */
        connect() {
            if (!this.oauthSafetyAck) {
                Espo.Ui.error(this.translate('googleConnectRiskCheckboxRequired', 'messages', 'ExternalAccount')
                    || this.translate('googleConnectRiskCheckboxRequired', 'labels', 'ExternalAccount'));

                return;
            }

            if (this.connectInProgress) {
                return;
            }

            this.connectInProgress = true;

            let exchangeStarted = false;

            const finishConnect = () => {
                this.connectInProgress = false;
            };

            const clearStoredState = () => {
                try {
                    sessionStorage.removeItem(OAUTH_STATE_STORAGE_KEY);
                } catch (e) {
                    // ignore
                }
            };

            const handleOAuthResponse = response => {
                if (exchangeStarted) {
                    return;
                }

                exchangeStarted = true;
                window.removeEventListener('message', onMessage);

                const expectedState = (() => {
                    try {
                        return sessionStorage.getItem(OAUTH_STATE_STORAGE_KEY) || '';
                    } catch (e) {
                        return '';
                    }
                })();
                clearStoredState();

                if (!expectedState || !response.state || response.state !== expectedState) {
                    Espo.Ui.error(this.translate('Error occurred'));
                    finishConnect();

                    return;
                }

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
                            // Background sync is manual-export-only; never leave other modes.
                            this.model.set('calendarSyncMode', 'none', {silent: true});
                            this.setConnected();
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

            const state = (() => {
                const bytes = new Uint8Array(24);
                window.crypto.getRandomValues(bytes);
                const token = Array.from(bytes, b => b.toString(16).padStart(2, '0')).join('');

                try {
                    sessionStorage.setItem(OAUTH_STATE_STORAGE_KEY, token);
                } catch (e) {
                    // ignore
                }

                return token;
            })();

            // Google rejects approval_prompt + prompt together; use prompt=consent only.
            const params = {
                client_id: this.clientId,
                redirect_uri: resolvedRedirectUri,
                scope: this.getMetadata().get(`integrations.${this.integration}.params.scope`),
                response_type: 'code',
                access_type: 'offline',
                prompt: 'consent',
                state: state,
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
                clearStoredState();
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
                    clearStoredState();
                    finishConnect();
                }
            }, 500);
        }

        getFieldsForSave() {
            // calendarSyncMode is server-forced to none — never send UI enum.
            return this.fieldList.filter(field => field !== 'calendarSyncMode');
        }

        save() {
            if (this.model.get('enabled')) {
                this.model.set('calendarSyncMode', 'none', {silent: true});
            } else {
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

            // Keep manual-only after fetchToModel (parent checkbox views must not revive modes).
            if (this.model.get('enabled')) {
                this.model.set('calendarSyncMode', 'none', {silent: true});
            }

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
