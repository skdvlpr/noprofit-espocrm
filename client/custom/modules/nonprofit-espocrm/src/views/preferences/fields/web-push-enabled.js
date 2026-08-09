define('nonprofit-espocrm:views/preferences/fields/web-push-enabled', ['views/fields/bool'], function (Dep) {

    /**
     * Browser push channel (independent from in-app bell / email prefs).
     * Toggle + permission check + optional delayed test push.
     */
    return Dep.extend({

        detailTemplateContent: `
            <div class="web-push-field">
                {{#if isNotEmpty}}
                    {{#if value}}
                        <span class="text-success web-push-status">{{statusOn}}</span>
                    {{else}}
                        <span class="text-muted web-push-status">{{statusOff}}</span>
                    {{/if}}
                {{else}}
                    <span class="text-muted web-push-status">{{statusOff}}</span>
                {{/if}}
            </div>
        `,

        editTemplateContent: `
            <div class="web-push-field">
                <label class="web-push-toggle">
                    <input type="checkbox" data-name="{{name}}" class="web-push-checkbox"
                        {{#if value}} checked{{/if}}>
                    <span class="web-push-toggle-label">{{fieldLabel}}</span>
                </label>
                <div class="web-push-actions margin-top-sm">
                    <button type="button" class="btn btn-default btn-sm"
                            data-action="checkPermissions">
                        <span class="fas fa-shield-alt"></span>
                        {{checkPermissionsLabel}}
                    </button>
                    <a href="{{helpUrl}}" target="_blank" rel="noopener noreferrer"
                       class="btn btn-link btn-sm web-push-help-link"
                       data-action="openPermissionHelp">
                        {{openSettingsLabel}}
                    </a>
                </div>
                <div class="web-push-test margin-top-sm">
                    <label class="small text-muted" for="web-push-delay-{{cid}}">{{delayLabel}}</label>
                    <div class="input-group input-group-sm" style="max-width: 280px;">
                        <input type="number" id="web-push-delay-{{cid}}"
                               class="form-control web-push-delay-seconds"
                               min="0" max="300" step="1" value="0"
                               title="{{sendTestTooltip}}">
                        <span class="input-group-btn">
                            <button type="button" class="btn btn-default"
                                    data-action="sendTestPush"
                                    title="{{sendTestTooltip}}">
                                <span class="fas fa-paper-plane"></span>
                                {{sendTestLabel}}
                            </button>
                        </span>
                    </div>
                </div>
                <p class="text-muted small web-push-help margin-top-sm">{{helpText}}</p>
                <div class="web-push-checklist hidden">
                    <div class="alert alert-warning web-push-checklist-box">
                        <strong>{{checklistTitle}}</strong>
                        <ul class="web-push-checklist-list"></ul>
                        <div class="web-push-checklist-actions">
                            <a href="{{helpUrl}}" target="_blank" rel="noopener noreferrer"
                               class="btn btn-default btn-sm web-push-help-link"
                               data-action="openPermissionHelp">
                                {{openSettingsLabel}}
                            </a>
                            <button type="button" class="btn btn-primary btn-sm"
                                    data-action="checkPermissions">
                                {{checkPermissionsLabel}}
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `,

        data: function () {
            const value = !!this.model.get(this.name);
            const lang = (this.getLanguage().name || 'en_US').toLowerCase();
            const helpUrl = lang.indexOf('it') === 0
                ? 'https://support.google.com/chrome/answer/3220216?hl=it'
                : 'https://support.google.com/chrome/answer/3220216?hl=en';

            return {
                name: this.name,
                value: value,
                isNotEmpty: true,
                cid: this.cid,
                fieldLabel: this.translate(this.name, 'fields', 'Preferences'),
                statusOn: this.translate('webPushStatusOn', 'labels', 'Preferences'),
                statusOff: this.translate('webPushStatusOff', 'labels', 'Preferences'),
                checkPermissionsLabel: this.translate('webPushCheckPermissions', 'labels', 'Preferences'),
                openSettingsLabel: this.translate('webPushOpenSettings', 'labels', 'Preferences'),
                sendTestLabel: this.translate('webPushSendTest', 'labels', 'Preferences'),
                delayLabel: this.translate('webPushDelaySeconds', 'labels', 'Preferences'),
                sendTestTooltip: this.translate('webPushSendTest', 'tooltips', 'Preferences'),
                helpText: this.translate('webPushHelp', 'tooltips', 'Preferences'),
                checklistTitle: this.translate('webPushChecklistTitle', 'labels', 'Preferences'),
                helpUrl: helpUrl,
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addActionHandler('checkPermissions', () => this.actionCheckPermissions());
            this.addActionHandler('sendTestPush', () => this.actionSendTestPush());
            this.addActionHandler('openPermissionHelp', (e) => {
                if (e && e.preventDefault) {
                    e.preventDefault();
                }

                this.actionOpenPermissionHelp();
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.mode === 'edit' || this.mode === 'detail') {
                this.$checkbox = this.$el.find('.web-push-checkbox');

                if (this.$checkbox.length) {
                    this.$checkbox.on('change', () => this.onToggle());
                }

                this.$el.find('[data-action="checkPermissions"]').off('click.webPush')
                    .on('click.webPush', e => {
                        e.preventDefault();
                        this.actionCheckPermissions();
                    });
                this.$el.find('[data-action="openPermissionHelp"]').off('click.webPush')
                    .on('click.webPush', e => {
                        e.preventDefault();
                        this.actionOpenPermissionHelp();
                    });
                this.$el.find('[data-action="sendTestPush"]').off('click.webPush')
                    .on('click.webPush', e => {
                        e.preventDefault();
                        this.actionSendTestPush();
                    });
            }
        },

        fetch: function () {
            const data = {};
            const $cb = this.$el.find('.web-push-checkbox');

            if ($cb.length) {
                data[this.name] = $cb.is(':checked');
            } else {
                data[this.name] = !!this.model.get(this.name);
            }

            return data;
        },

        onToggle: function () {
            const wantOn = this.$el.find('.web-push-checkbox').is(':checked');
            const helper = this.getHelperApi();

            if (!helper) {
                this.forceOff(this.translate('webPushUnsupported', 'messages', 'Preferences'));

                return;
            }

            if (!wantOn) {
                this.hideChecklist();
                this.model.set(this.name, false, {ui: false});
                helper.disable().catch(() => {});

                return;
            }

            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            helper.enable()
                .then(() => {
                    this.hideChecklist();
                    this.model.set(this.name, true, {ui: false});
                    Espo.Ui.success(this.translate('webPushEnabledOk', 'messages', 'Preferences'));
                })
                .catch(err => {
                    this.forceOff(
                        (err && err.message) ||
                        this.translate('webPushEnableFailed', 'messages', 'Preferences')
                    );

                    if (err && (
                        err.code === 'permissions' ||
                        err.code === 'unsupported' ||
                        err.code === 'push-service'
                    )) {
                        this.showChecklist(err.diagnose || (helper.diagnose && helper.diagnose()));
                    }
                });
        },

        actionCheckPermissions: function () {
            const helper = this.getHelperApi();

            if (!helper) {
                Espo.Ui.error(this.translate('webPushUnsupported', 'messages', 'Preferences'));

                return;
            }

            const d = helper.diagnose();

            if (d.ok && d.permission === 'granted') {
                this.hideChecklist();
                Espo.Ui.success(this.translate('webPushPermissionsOk', 'messages', 'Preferences'));

                return;
            }

            if (d.canRequest) {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                helper.requestPermission()
                    .then(permission => {
                        if (permission === 'granted') {
                            this.hideChecklist();
                            Espo.Ui.success(this.translate('webPushPermissionsOk', 'messages', 'Preferences'));
                        } else {
                            this.showChecklist(helper.diagnose());
                            Espo.Ui.warning(this.translate('webPushPermissionsBad', 'messages', 'Preferences'));
                        }
                    })
                    .catch(() => {
                        this.showChecklist(helper.diagnose());
                        Espo.Ui.warning(this.translate('webPushPermissionsBad', 'messages', 'Preferences'));
                    });

                return;
            }

            this.showChecklist(d);
            Espo.Ui.warning(this.translate('webPushPermissionsBad', 'messages', 'Preferences'));
        },

        actionOpenPermissionHelp: function () {
            const helper = this.getHelperApi();

            if (helper && helper.openPermissionHelp) {
                helper.openPermissionHelp();
            }
        },

        actionSendTestPush: function () {
            const raw = this.$el.find('.web-push-delay-seconds').val();
            const delay = raw === '' || raw === null ? 0 : parseInt(raw, 10);

            if (isNaN(delay) || delay < 0 || delay > 300) {
                Espo.Ui.error(this.translate('webPushTestInvalidDelay', 'messages', 'Preferences'));

                return;
            }

            const mapFailReason = (res) => {
                const reason = res && res.reason;

                if (reason === 'stale_subscription') {
                    return this.translate('webPushTestStale', 'messages', 'Preferences');
                }

                if (reason === 'send_failed') {
                    return this.translate('webPushTestSendFailed', 'messages', 'Preferences');
                }

                return this.translate('webPushTestFailed', 'messages', 'Preferences');
            };

            const postTest = (allowResubscribe) => {
                return Espo.Ajax.postRequest('WebPush/action/test', {delaySeconds: 0})
                    .then(res => {
                        const sent = res && typeof res.sent === 'number' ? res.sent : 0;

                        if (sent > 0) {
                            Espo.Ui.success(
                                this.translate('webPushTestSent', 'messages', 'Preferences')
                                    .replace('{sent}', String(sent))
                            );

                            this.model.set(this.name, true, {ui: false});
                            this.$el.find('.web-push-checkbox').prop('checked', true);

                            return;
                        }

                        const reason = res && res.reason;
                        const helper = this.getHelperApi();

                        // Stale FCM endpoint (410) or missing row — re-subscribe once then retry.
                        if (
                            allowResubscribe &&
                            helper &&
                            (reason === 'no_subscription' || reason === 'stale_subscription')
                        ) {
                            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                            return helper.enable()
                                .then(() => postTest(false))
                                .catch(err => {
                                    this.forceOff(
                                        (err && err.message) ||
                                        this.translate('webPushEnableFailed', 'messages', 'Preferences')
                                    );
                                });
                        }

                        Espo.Ui.error(mapFailReason(res));
                    })
                    .catch(() => {
                        Espo.Ui.error(this.translate('webPushTestFailed', 'messages', 'Preferences'));
                    });
            };

            const send = () => {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                const helper = this.getHelperApi();

                // Ensure this browser has a live PushSubscription before testing.
                const ensureLocal = helper && helper.hasLocalSubscription
                    ? helper.hasLocalSubscription()
                    : Promise.resolve(false);

                ensureLocal.then(hasLocal => {
                    if (hasLocal) {
                        return postTest(true);
                    }

                    if (!helper) {
                        Espo.Ui.error(this.translate('webPushUnsupported', 'messages', 'Preferences'));

                        return;
                    }

                    return helper.enable()
                        .then(() => {
                            this.model.set(this.name, true, {ui: false});
                            this.$el.find('.web-push-checkbox').prop('checked', true);

                            return postTest(false);
                        })
                        .catch(err => {
                            this.forceOff(
                                (err && err.message) ||
                                this.translate('webPushEnableFailed', 'messages', 'Preferences')
                            );
                        });
                });
            };

            if (delay > 0) {
                if (this._testPushTimer) {
                    clearTimeout(this._testPushTimer);
                }

                Espo.Ui.success(
                    this.translate('webPushTestScheduled', 'messages', 'Preferences')
                        .replace('{seconds}', String(delay))
                );

                this._testPushTimer = setTimeout(send, delay * 1000);

                return;
            }

            send();
        },

        showChecklist: function (diagnose) {
            const helper = this.getHelperApi();
            const $box = this.$el.find('.web-push-checklist');
            const $list = $box.find('.web-push-checklist-list');
            const lines = [];

            if (diagnose && diagnose.issues && diagnose.issues.length) {
                diagnose.issues.forEach(i => lines.push(i));
            }

            if (helper && helper.checklistLines) {
                helper.checklistLines().forEach(i => lines.push(i));
            }

            $list.empty();
            lines.forEach(line => {
                $list.append($('<li>').text(line));
            });
            $box.removeClass('hidden');
        },

        hideChecklist: function () {
            this.$el.find('.web-push-checklist').addClass('hidden');
        },

        forceOff: function (message) {
            if (message) {
                Espo.Ui.error(message);
            }

            this.model.set(this.name, false, {ui: false});
            this.$el.find('.web-push-checkbox').prop('checked', false);
        },

        getHelperApi: function () {
            return window.SafehouseWebPush || null;
        },
    });
});
