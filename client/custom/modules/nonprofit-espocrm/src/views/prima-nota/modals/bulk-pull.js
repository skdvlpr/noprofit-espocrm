define('nonprofit-espocrm:views/prima-nota/modals/bulk-pull', ['views/modal'], function (Dep) {

    return Dep.extend({

        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: false,

        templateContent: `
            <div class="bulk-pull-modal">
                <div class="bulk-pull-form" data-name="bulkPullForm">
                    <p class="bulk-pull-hint">{{hint}}</p>

                    <section class="bulk-pull-section">
                        <h5 class="bulk-pull-section-title">{{providersLabel}}</h5>
                        <div class="bulk-pull-options bulk-pull-options-stack">
                            {{#each providers}}
                                <label class="bulk-pull-option {{#unless enabled}}is-disabled{{/unless}}">
                                    <input type="checkbox" data-provider="{{id}}"
                                        {{#if checked}}checked{{/if}}
                                        {{#unless enabled}}disabled{{/unless}}>
                                    <span class="bulk-pull-option-box" aria-hidden="true"></span>
                                    <span class="bulk-pull-option-text">
                                        {{label}}{{#unless enabled}}
                                            <em class="bulk-pull-soon">({{../comingSoon}})</em>
                                        {{/unless}}
                                    </span>
                                </label>
                            {{/each}}
                        </div>
                    </section>

                    <section class="bulk-pull-section">
                        <h5 class="bulk-pull-section-title">{{currenciesLabel}}</h5>
                        <div class="bulk-pull-options bulk-pull-options-grid">
                            {{#each currencies}}
                                <label class="bulk-pull-option">
                                    <input type="checkbox" data-currency="{{id}}"
                                        {{#if checked}}checked{{/if}}>
                                    <span class="bulk-pull-option-box" aria-hidden="true"></span>
                                    <span class="bulk-pull-option-text">{{label}}</span>
                                </label>
                            {{/each}}
                        </div>
                        <p class="bulk-pull-note">{{currencyHint}}</p>
                    </section>

                    <section class="bulk-pull-section">
                        <h5 class="bulk-pull-section-title">{{modeLabel}}</h5>
                        <div class="bulk-pull-options bulk-pull-options-stack">
                            <label class="bulk-pull-option">
                                <input type="radio" name="bulkPullMode" value="all" checked>
                                <span class="bulk-pull-option-box is-radio" aria-hidden="true"></span>
                                <span class="bulk-pull-option-text">{{modeAllLabel}}</span>
                            </label>
                            <label class="bulk-pull-option">
                                <input type="radio" name="bulkPullMode" value="from_date">
                                <span class="bulk-pull-option-box is-radio" aria-hidden="true"></span>
                                <span class="bulk-pull-option-text">{{modeFromDateLabel}}</span>
                            </label>
                        </div>
                        <div class="bulk-pull-date" data-name="fromDate" hidden>
                            <label class="bulk-pull-date-label" for="bulk-pull-from-date">{{fromDateLabel}}</label>
                            <input id="bulk-pull-from-date" type="date"
                                class="form-control bulk-pull-date-input" data-name="fromDateInput">
                        </div>
                    </section>
                </div>

                <div class="bulk-pull-progress" data-name="bulkPullProgress" hidden>
                    <p class="bulk-pull-progress-title" data-name="progressTitle">{{inProgress}}</p>
                    <div class="bulk-pull-progress-track" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-busy="true">
                        <div class="bulk-pull-progress-indeterminate"></div>
                    </div>
                    <p class="bulk-pull-progress-hint" data-name="progressHint">{{progressHint}}</p>

                    <div class="bulk-pull-log" data-name="bulkPullLog">
                        <div class="bulk-pull-log-head">
                            <span class="bulk-pull-log-title">{{logLabel}}</span>
                            <button type="button" class="btn btn-default btn-sm bulk-pull-log-copy"
                                data-action="copyLog">{{copyLogLabel}}</button>
                        </div>
                        <pre class="bulk-pull-log-body" data-name="logBody" aria-live="polite"></pre>
                    </div>
                </div>
            </div>
        `,

        data() {
            return {
                hint: this.translate('bulkPullHint', 'messages', 'PrimaNota'),
                providersLabel: this.translate('bulkPullProviders', 'labels', 'PrimaNota'),
                currenciesLabel: this.translate('bulkPullCurrencies', 'labels', 'PrimaNota'),
                currencyHint: this.translate('bulkPullCurrencyHint', 'messages', 'PrimaNota'),
                modeLabel: this.translate('bulkPullMode', 'labels', 'PrimaNota'),
                modeAllLabel: this.translate('bulkPullModeAll', 'labels', 'PrimaNota'),
                modeFromDateLabel: this.translate('bulkPullModeFromDate', 'labels', 'PrimaNota'),
                fromDateLabel: this.translate('bulkPullFromDate', 'labels', 'PrimaNota'),
                comingSoon: this.translate('bulkPullComingSoon', 'labels', 'PrimaNota'),
                inProgress: this.translate('bulkPullInProgress', 'messages', 'PrimaNota'),
                progressHint: this.translate('bulkPullProgressHint', 'messages', 'PrimaNota'),
                logLabel: this.translate('bulkPullLog', 'labels', 'PrimaNota'),
                copyLogLabel: this.translate('bulkPullCopyLog', 'labels', 'PrimaNota'),
                providers: this.providerOptions,
                currencies: this.currencyOptions,
            };
        },

        setup() {
            this.headerText = this.translate('bulkPullFromProviders', 'labels', 'PrimaNota');
            this.clientLogLines = [];
            this.heartbeatTimer = null;
            this.providerOptions = [
                {id: 'Stripe', label: 'Stripe', enabled: true, checked: true},
                {id: 'Satispay', label: 'Satispay', enabled: false, checked: false},
                {id: 'Revolut', label: 'Revolut', enabled: false, checked: false},
                {id: 'BankTransfer', label: 'Bank transfer', enabled: false, checked: false},
                {id: 'BankApp', label: 'Bank app', enabled: false, checked: false},
            ];
            this.currencyOptions = [
                {id: 'EUR', label: 'EUR', checked: true},
                {id: 'USD', label: 'USD', checked: false},
                {id: 'GBP', label: 'GBP', checked: false},
                {id: 'CHF', label: 'CHF', checked: false},
                {id: 'PLN', label: 'PLN', checked: false},
            ];

            this.buttonList = [
                {
                    name: 'run',
                    label: this.translate('bulkPullRun', 'labels', 'PrimaNota'),
                    style: 'danger',
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];
        },

        afterRender() {
            this.$el.find('input[name="bulkPullMode"]').on('change', () => {
                this.syncFromDateVisibility();
            });
            this.$el.find('[data-action="copyLog"]').on('click', () => this.copyLog());
            this.syncFromDateVisibility();
            this.setProgressVisible(false);
        },

        onRemove() {
            this.stopHeartbeat();
        },

        syncFromDateVisibility() {
            const mode = this.$el.find('input[name="bulkPullMode"]:checked').val();
            const $wrap = this.$el.find('[data-name="fromDate"]');

            if (mode === 'from_date') {
                $wrap.prop('hidden', false);
            }
            else {
                $wrap.prop('hidden', true);
            }
        },

        setProgressVisible(visible) {
            const $form = this.$el.find('[data-name="bulkPullForm"]');
            const $progress = this.$el.find('[data-name="bulkPullProgress"]');

            $form.prop('hidden', !!visible);
            $progress.prop('hidden', !visible);
        },

        formatLogTime() {
            const d = new Date();

            return String(d.getHours()).padStart(2, '0') + ':' +
                String(d.getMinutes()).padStart(2, '0') + ':' +
                String(d.getSeconds()).padStart(2, '0');
        },

        appendLog(message) {
            const line = '[' + this.formatLogTime() + '] ' + String(message || '');

            this.clientLogLines.push(line);

            const $body = this.$el.find('[data-name="logBody"]');

            if ($body.length) {
                $body.text(this.clientLogLines.join('\n'));
                $body.scrollTop($body[0].scrollHeight);
            }
        },

        mergeServerLog(response) {
            const serverLog = response && response.log ? response.log : [];

            if (!Array.isArray(serverLog) || !serverLog.length) {
                return;
            }

            this.appendLog('--- server ---');
            serverLog.forEach(line => {
                this.clientLogLines.push(String(line));
            });

            const $body = this.$el.find('[data-name="logBody"]');

            if ($body.length) {
                $body.text(this.clientLogLines.join('\n'));
                $body.scrollTop($body[0].scrollHeight);
            }
        },

        startHeartbeat() {
            this.stopHeartbeat();

            let seconds = 0;

            this.heartbeatTimer = setInterval(() => {
                seconds += 15;
                this.appendLog(
                    this.translate('bulkPullStillWorking', 'messages', 'PrimaNota')
                        .replace('{seconds}', String(seconds))
                );
            }, 15000);
        },

        stopHeartbeat() {
            if (this.heartbeatTimer) {
                clearInterval(this.heartbeatTimer);
                this.heartbeatTimer = null;
            }
        },

        copyLog() {
            const text = this.clientLogLines.join('\n');

            if (!text) {
                Espo.Ui.warning(this.translate('bulkPullLogEmpty', 'messages', 'PrimaNota'));

                return;
            }

            const done = () => {
                Espo.Ui.success(this.translate('bulkPullLogCopied', 'messages', 'PrimaNota'));
            };

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(text).then(done).catch(() => {
                    this.fallbackCopyLog(text, done);
                });

                return;
            }

            this.fallbackCopyLog(text, done);
        },

        fallbackCopyLog(text, done) {
            const $ta = $('<textarea>')
                .css({position: 'fixed', left: '-9999px', top: '0'})
                .val(text)
                .appendTo(document.body);

            $ta[0].select();

            try {
                document.execCommand('copy');
                done();
            }
            catch (e) {
                Espo.Ui.error(this.translate('bulkPullLogCopyFailed', 'messages', 'PrimaNota'));
            }

            $ta.remove();
        },

        buildSuccessMessage(response) {
            const created = response && response.created != null ? Number(response.created) : 0;
            const updated = response && response.updated != null ? Number(response.updated) : 0;
            const skipped = response && response.skipped != null ? Number(response.skipped) : 0;
            const failed = response && response.failed != null ? Number(response.failed) : 0;
            const duplicate = response && response.duplicate != null ? Number(response.duplicate) : 0;
            const markedInviato = response && response.markedInviato != null
                ? Number(response.markedInviato)
                : 0;
            const skippedCurrencies = response && response.skippedCurrencies
                ? response.skippedCurrencies
                : [];

            let msg = this.translate('bulkPullSuccess', 'messages', 'PrimaNota')
                .replace('{created}', String(created))
                .replace('{updated}', String(updated + duplicate))
                .replace('{skipped}', String(skipped))
                .replace('{failed}', String(failed));

            if (markedInviato > 0) {
                msg = msg + ' ' + this.translate('bulkPullMarkedInviato', 'messages', 'PrimaNota')
                    .replace('{count}', String(markedInviato));
            }

            if (response && response.truncated) {
                msg = msg + ' ' + this.translate('bulkPullTruncated', 'messages', 'PrimaNota');
            }

            if (response && response.unsupportedProviders && response.unsupportedProviders.length) {
                msg = msg + ' ' + this.translate('bulkPullUnsupported', 'messages', 'PrimaNota')
                    .replace('{list}', response.unsupportedProviders.join(', '));
            }

            if (skippedCurrencies.length) {
                msg = msg + ' ' + this.translate('bulkPullSkippedCurrencies', 'messages', 'PrimaNota')
                    .replace('{list}', skippedCurrencies.join(', '));
            }

            return {msg: msg, failed: failed};
        },

        actionRun() {
            const providers = [];

            this.$el.find('input[data-provider]:checked:not(:disabled)').each(function () {
                providers.push($(this).attr('data-provider'));
            });

            if (!providers.length) {
                Espo.Ui.error(this.translate('bulkPullProvidersRequired', 'messages', 'PrimaNota'));

                return;
            }

            const currencies = [];

            this.$el.find('input[data-currency]:checked').each(function () {
                currencies.push($(this).attr('data-currency'));
            });

            if (!currencies.length) {
                Espo.Ui.error(this.translate('bulkPullCurrenciesRequired', 'messages', 'PrimaNota'));

                return;
            }

            const mode = this.$el.find('input[name="bulkPullMode"]:checked').val() || 'all';
            let fromDate = null;

            if (mode === 'from_date') {
                fromDate = String(this.$el.find('[data-name="fromDateInput"]').val() || '').trim();

                if (!fromDate) {
                    Espo.Ui.error(this.translate('bulkPullFromDateRequired', 'messages', 'PrimaNota'));

                    return;
                }
            }

            this.disableButton('run');
            this.disableButton('cancel');
            this.clientLogLines = [];
            this.setProgressVisible(true);
            this.appendLog(this.translate('bulkPullLogClientStart', 'messages', 'PrimaNota'));
            this.appendLog(
                'REQUEST providers=' + providers.join(',') +
                ' currencies=' + currencies.join(',') +
                ' mode=' + mode +
                (fromDate ? ' fromDate=' + fromDate : '')
            );
            Espo.Ui.notify(this.translate('bulkPullInProgress', 'messages', 'PrimaNota'));
            this.startHeartbeat();

            Espo.Ajax.postRequest('PrimaNota/action/bulkPullFromProviders', {
                providers: providers,
                currencies: currencies,
                mode: mode,
                fromDate: fromDate,
                maxItems: 200,
            }, {
                timeout: 0,
            })
                .then(response => {
                    this.stopHeartbeat();
                    this.appendLog('RESPONSE ok from CRM');
                    this.mergeServerLog(response);
                    this.appendLog(this.translate('bulkPullLogRefreshingList', 'messages', 'PrimaNota'));

                    const summary = this.buildSuccessMessage(response);

                    // Close only after list refresh so rows appear without Ctrl+R.
                    this.trigger('done', response, (refreshError) => {
                        Espo.Ui.notify(false);

                        if (refreshError) {
                            this.appendLog('LIST refresh ERROR: ' + String(refreshError));
                        }
                        else {
                            this.appendLog(this.translate('bulkPullLogListRefreshed', 'messages', 'PrimaNota'));
                        }

                        if (summary.failed > 0) {
                            Espo.Ui.warning(summary.msg);
                        }
                        else {
                            Espo.Ui.success(summary.msg);
                        }

                        this.close();
                    });
                })
                .catch(xhr => {
                    this.stopHeartbeat();
                    Espo.Ui.notify(false);

                    let message = this.translate('bulkPullFailed', 'messages', 'PrimaNota');

                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = String(xhr.responseJSON.message);
                    }
                    else if (xhr && xhr.getResponseHeader) {
                        const reason = xhr.getResponseHeader('X-Status-Reason');

                        if (reason) {
                            message = reason;
                        }
                    }

                    this.appendLog('RESPONSE error: ' + message);
                    this.appendLog(this.translate('bulkPullLogRefreshingList', 'messages', 'PrimaNota'));

                    // Partial import may already be in CRM — refresh list anyway.
                    this.trigger('done', null, () => {
                        this.enableButton('run');
                        this.enableButton('cancel');
                        // Keep progress + log visible so user can copy; hide spinner track.
                        this.$el.find('.bulk-pull-progress-track').prop('hidden', true);
                        this.$el.find('[data-name="progressTitle"]')
                            .text(this.translate('bulkPullFinishedWithError', 'messages', 'PrimaNota'));
                        this.$el.find('[data-name="progressHint"]')
                            .text(message);
                        Espo.Ui.error(message);
                    });
                });
        },
    });
});
