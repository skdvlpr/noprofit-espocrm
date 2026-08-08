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

        setProgressStage(title, hint) {
            const $title = this.$el.find('[data-name="progressTitle"]');
            const $hint = this.$el.find('[data-name="progressHint"]');

            if ($title.length && title) {
                $title.text(title);
            }

            if ($hint.length && hint != null) {
                $hint.text(hint);
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

        appendStageLog(messageKey, replacements) {
            let msg = this.translate(messageKey, 'messages', 'PrimaNota');
            const map = replacements || {};

            Object.keys(map).forEach(key => {
                msg = msg.replace('{' + key + '}', String(map[key]));
            });

            this.appendLog(msg);
            this.setProgressStage(
                this.translate('bulkPullInProgress', 'messages', 'PrimaNota'),
                msg
            );
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

        emptyTotals() {
            return {
                created: 0,
                updated: 0,
                duplicate: 0,
                skipped: 0,
                failed: 0,
                markedInviato: 0,
                statusRefreshed: 0,
                scanned: 0,
                truncated: false,
                unsupportedProviders: [],
                skippedCurrencies: [],
            };
        },

        addTotals(totals, response) {
            totals.created += response && response.created != null ? Number(response.created) : 0;
            totals.updated += response && response.updated != null ? Number(response.updated) : 0;
            totals.duplicate += response && response.duplicate != null ? Number(response.duplicate) : 0;
            totals.skipped += response && response.skipped != null ? Number(response.skipped) : 0;
            totals.failed += response && response.failed != null ? Number(response.failed) : 0;
            totals.markedInviato += response && response.markedInviato != null
                ? Number(response.markedInviato)
                : 0;
            totals.statusRefreshed += response && response.statusRefreshed != null
                ? Number(response.statusRefreshed)
                : 0;
            totals.scanned += response && response.scanned != null ? Number(response.scanned) : 0;
            // Keep truncated from the latest batch (true only if more remain unprocessed).
            totals.truncated = !!(response && response.truncated);

            if (response && response.unsupportedProviders) {
                response.unsupportedProviders.forEach(p => {
                    if (totals.unsupportedProviders.indexOf(p) === -1) {
                        totals.unsupportedProviders.push(p);
                    }
                });
            }

            if (response && response.skippedCurrencies) {
                response.skippedCurrencies.forEach(c => {
                    if (totals.skippedCurrencies.indexOf(c) === -1) {
                        totals.skippedCurrencies.push(c);
                    }
                });
            }
        },

        buildSuccessMessage(totals) {
            let msg = this.translate('bulkPullSuccess', 'messages', 'PrimaNota')
                .replace('{created}', String(totals.created))
                .replace('{updated}', String(totals.updated + totals.duplicate))
                .replace('{skipped}', String(totals.skipped))
                .replace('{failed}', String(totals.failed));

            if (totals.markedInviato > 0) {
                msg = msg + ' ' + this.translate('bulkPullMarkedInviato', 'messages', 'PrimaNota')
                    .replace('{count}', String(totals.markedInviato));
            }

            if (totals.truncated) {
                msg = msg + ' ' + this.translate('bulkPullTruncated', 'messages', 'PrimaNota');
            }

            if (totals.unsupportedProviders.length) {
                msg = msg + ' ' + this.translate('bulkPullUnsupported', 'messages', 'PrimaNota')
                    .replace('{list}', totals.unsupportedProviders.join(', '));
            }

            if (totals.skippedCurrencies.length) {
                msg = msg + ' ' + this.translate('bulkPullSkippedCurrencies', 'messages', 'PrimaNota')
                    .replace('{list}', totals.skippedCurrencies.join(', '));
            }

            return {msg: msg, failed: totals.failed};
        },

        requestBatch(payload, batchNumber) {
            this.appendStageLog('bulkPullStageRequest', {batch: batchNumber});
            this.appendStageLog('bulkPullStageScan', {});

            return Espo.Ajax.postRequest('PrimaNota/action/bulkPullFromProviders', payload, {
                timeout: 0,
            });
        },

        summarizeBatch(response, batchNumber) {
            const scanned = response && response.scanned != null ? Number(response.scanned) : 0;
            const created = response && response.created != null ? Number(response.created) : 0;
            const updated = response && response.updated != null ? Number(response.updated) : 0;
            const duplicate = response && response.duplicate != null ? Number(response.duplicate) : 0;
            const skipped = response && response.skipped != null ? Number(response.skipped) : 0;
            const failed = response && response.failed != null ? Number(response.failed) : 0;
            const markedInviato = response && response.markedInviato != null
                ? Number(response.markedInviato)
                : 0;
            const payouts = response && response.statusRefreshed != null
                ? Number(response.statusRefreshed)
                : 0;

            this.appendStageLog('bulkPullStageReceived', {count: scanned});
            this.appendStageLog('bulkPullStageStatuses', {});
            this.appendStageLog('bulkPullStageStatusesDone', {
                count: markedInviato,
                payouts: payouts,
            });
            this.appendStageLog('bulkPullStageBatchDone', {
                batch: batchNumber,
                created: created,
                updated: updated + duplicate,
                skipped: skipped,
                failed: failed,
            });
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

            const chunkSize = 25;
            const maxBatches = 20;
            let startingAfter = null;
            const payloadBase = {
                providers: providers,
                currencies: currencies,
                mode: mode,
                fromDate: fromDate,
                maxItems: chunkSize,
            };

            this.disableButton('run');
            this.disableButton('cancel');
            this.clientLogLines = [];
            this.setProgressVisible(true);
            this.setProgressStage(
                this.translate('bulkPullInProgress', 'messages', 'PrimaNota'),
                this.translate('bulkPullProgressHint', 'messages', 'PrimaNota')
            );
            this.appendLog(this.translate('bulkPullLogClientStart', 'messages', 'PrimaNota'));
            this.appendLog(
                'REQUEST providers=' + providers.join(',') +
                ' currencies=' + currencies.join(',') +
                ' mode=' + mode +
                (fromDate ? ' fromDate=' + fromDate : '') +
                ' chunkSize=' + chunkSize
            );
            Espo.Ui.notify(this.translate('bulkPullInProgress', 'messages', 'PrimaNota'));

            const totals = this.emptyTotals();
            let batch = 1;

            const runNext = () => {
                const payload = Object.assign({}, payloadBase);

                if (startingAfter) {
                    payload.startingAfter = startingAfter;
                }

                return this.requestBatch(payload, batch)
                    .then(response => {
                        this.mergeServerLog(response);
                        this.summarizeBatch(response, batch);
                        this.addTotals(totals, response);

                        const next = response && response.nextStartingAfter
                            ? String(response.nextStartingAfter)
                            : '';
                        const truncated = !!(response && response.truncated);
                        const continueMore = truncated && next !== '' && batch < maxBatches;

                        if (continueMore) {
                            startingAfter = next;
                        }

                        // Refresh list after each batch so rows appear progressively.
                        return new Promise(resolve => {
                            this.appendStageLog('bulkPullStageRefreshingList', {});
                            this.trigger('done', response, (refreshError) => {
                                if (refreshError) {
                                    this.appendLog('LIST refresh ERROR: ' + String(refreshError));
                                }
                                else {
                                    this.appendStageLog('bulkPullStageListRefreshed', {});
                                }

                                resolve({continueMore: continueMore});
                            });
                        });
                    })
                    .then(state => {
                        if (state && state.continueMore) {
                            batch += 1;
                            this.appendStageLog('bulkPullStageNextBatch', {});

                            return runNext();
                        }

                        return null;
                    });
            };

            runNext()
                .then(() => {
                    Espo.Ui.notify(false);

                    const summary = this.buildSuccessMessage(totals);

                    if (summary.failed > 0) {
                        Espo.Ui.warning(summary.msg);
                    }
                    else {
                        Espo.Ui.success(summary.msg);
                    }

                    this.close();
                })
                .catch(xhr => {
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
                    this.appendStageLog('bulkPullStageRefreshingList', {});

                    this.trigger('done', null, () => {
                        this.enableButton('run');
                        this.enableButton('cancel');
                        this.$el.find('.bulk-pull-progress-track').prop('hidden', true);
                        this.setProgressStage(
                            this.translate('bulkPullFinishedWithError', 'messages', 'PrimaNota'),
                            message
                        );
                        Espo.Ui.error(message);
                    });
                });
        },
    });
});
