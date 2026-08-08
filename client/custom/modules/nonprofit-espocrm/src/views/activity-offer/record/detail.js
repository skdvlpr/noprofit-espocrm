define('nonprofit-espocrm:views/activity-offer/record/detail', [
    'views/record/detail',
    'nonprofit-espocrm:views/activity-offer/record/place-description-layout',
    'helpers/action-item-setup',
    'nonprofit-espocrm:handlers/activity-offer/shift-actions',
], function (Dep, PlaceDescriptionLayout, ActionItemSetup, ShiftActions) {

    ActionItemSetup = ActionItemSetup.default || ActionItemSetup;

    return Dep.extend(Object.assign({}, PlaceDescriptionLayout, {

        template: 'nonprofit-espocrm:activity-offer/record/detail',

        bottomView: 'nonprofit-espocrm:views/activity-offer/record/detail-bottom',

        planningView: 'nonprofit-espocrm:views/activity-offer/record/detail-planning',

        pendingUpdateTimerId: null,

        lifecycleFetchTimerId: null,

        lifecycleFetchTimers: null,

        lifecycleFetching: false,

        liveRefreshTimerId: null,

        /** Poll interval while the plan detail is open (ms). */
        liveRefreshIntervalMs: 4000,

        // Banner uses distinct action names so Espo handleAction does not bind them
        // to detailButtonList handler items named sendPendingUpdate / extend*.
        events: {
            'click [data-action="bannerSendPendingUpdate"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (!this.getAcl().checkModel(this.model, 'edit')) {
                    Espo.Ui.error(this.translate('Access denied'));

                    return;
                }

                this.actionSendPendingUpdate();
            },
            'click [data-action="bannerExtendPendingUpdate"]': function (e) {
                e.preventDefault();
                e.stopPropagation();

                if (!this.getAcl().checkModel(this.model, 'edit')) {
                    Espo.Ui.error(this.translate('Access denied'));

                    return;
                }

                this.actionExtendPendingUpdate();
            },
        },

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupPlaceDescriptionLayout();

            this.listenTo(this.model, 'change:status change:pendingNotifyKind change:pendingNotifyAt', () => {
                // Full reRender only when banner visibility/kind changes — not on
                // every sync — so the live MM:SS interval is not killed mid-tick.
                this.updatePendingUpdateBanner();
            });

            this.listenTo(this.model, 'sync', () => {
                if (!this.isRendered()) {
                    return;
                }

                const $timer = this.$el.find('.activity-offer-pending-update-timer');

                if ($timer.length) {
                    $timer.text(this.formatPendingUpdateCountdown());
                }

                this.startPendingUpdateCountdown();
            });

            // AfterSave hooks set Updated/pending* on a second silent save — refresh
            // so the yellow banner + header buttons appear without F5.
            this.listenTo(this.model, 'after:save', () => {
                this.refreshOfferLifecycleState();
            });

            this.listenTo(this.model, 'update-related:slots update-related:coverage', () => {
                this.refreshOfferLifecycleState();
            });

            this.listenTo(this, 'after:render', () => {
                this.startLiveRefresh();
            });
        },

        data: function () {
            const data = Dep.prototype.data.call(this);

            return Object.assign(data, this.getPendingUpdateBannerData());
        },

        /**
         * Espo 10 ignores legacy clientDefs.menu.detail.buttons.
         * Load lifecycle actions as visible header buttons (next to Edit).
         */
        setupActionItems: function () {
            Dep.prototype.setupActionItems.call(this);

            if (this.buttonsDisabled || this.type !== this.TYPE_DETAIL) {
                return;
            }

            const actionItemSetup = new ActionItemSetup();

            actionItemSetup.setup({
                view: this,
                type: 'detailButtonList',
                waitFunc: promise => this.wait(promise),
                addFunc: item => this.addButton(item),
                showFunc: name => this.showActionItem(name),
                hideFunc: name => this.hideActionItem(name),
                enableFunc: name => this.enableActionItem(name),
                disableFunc: name => this.disableActionItem(name),
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.afterRenderPlaceDescriptionLayout();
            this.startPendingUpdateCountdown();
            this.startLiveRefresh();
        },

        onRemove: function () {
            this.clearPendingUpdateCountdown();
            this.clearLifecycleFetchTimer();
            this.clearLiveRefresh();

            if (Dep.prototype.onRemove) {
                Dep.prototype.onRemove.call(this);
            }
        },

        /**
         * Keep yellow banner + coverage/match tables fresh without F5.
         * Slot AfterSave may write pendingNotify* silently after the first response.
         */
        startLiveRefresh: function () {
            this.clearLiveRefresh();

            if (!this.model || !this.model.id || this.type !== this.TYPE_DETAIL) {
                return;
            }

            this.liveRefreshTimerId = window.setInterval(() => {
                if (!this.isRendered() || !this.model || !this.model.id || this.lifecycleFetching) {
                    return;
                }

                this.lifecycleFetching = true;

                this.model.fetch()
                    .then(() => {
                        this.model.trigger('update-related:coverage');
                        this.updatePendingUpdateBanner();
                    })
                    .finally(() => {
                        this.lifecycleFetching = false;
                    });
            }, this.liveRefreshIntervalMs);
        },

        clearLiveRefresh: function () {
            if (this.liveRefreshTimerId) {
                window.clearInterval(this.liveRefreshTimerId);
                this.liveRefreshTimerId = null;
            }
        },

        getPendingUpdateBannerData: function () {
            const status = this.model.get('status');
            const kind = this.model.get('pendingNotifyKind');
            const show = status === 'Updated' && !!kind;
            const canEditPendingUpdate = !!(
                this.getAcl && this.getAcl().checkModel(this.model, 'edit')
            );

            if (!show) {
                return {
                    showPendingUpdateBanner: false,
                    canEditPendingUpdate: false,
                    pendingUpdateBannerText: '',
                    pendingUpdateCountdown: '',
                };
            }

            const msgKey = kind === 'hard'
                ? 'pendingUpdateBannerHard'
                : 'pendingUpdateBannerSoft';

            return {
                showPendingUpdateBanner: true,
                canEditPendingUpdate: canEditPendingUpdate,
                pendingUpdateBannerText: this.translate(msgKey, 'messages', 'ActivityOffer'),
                pendingUpdateCountdown: this.formatPendingUpdateCountdown(),
            };
        },

        formatPendingUpdateCountdown: function () {
            const at = this.model.get('pendingNotifyAt');

            if (!at) {
                return '';
            }

            const dateTime = this.getDateTime();
            // pendingNotifyAt is stored as system UTC (Y-m-d H:i:s).
            const target = moment.utc(
                at,
                dateTime.internalDateTimeFormat || 'YYYY-MM-DD HH:mm:ss',
                true
            );
            const now = moment.utc();

            if (!target.isValid()) {
                return '';
            }

            const seconds = Math.max(0, Math.ceil(target.diff(now, 'seconds', true)));

            if (seconds <= 0) {
                return this.translate('pendingUpdateSendingSoon', 'messages', 'ActivityOffer');
            }

            const mm = String(Math.floor(seconds / 60)).padStart(2, '0');
            const ss = String(seconds % 60).padStart(2, '0');

            return this.translate('pendingUpdateCountdown', 'messages', 'ActivityOffer')
                .replace('{time}', mm + ':' + ss);
        },

        updatePendingUpdateBanner: function () {
            if (!this.isRendered()) {
                return;
            }

            const next = this.getPendingUpdateBannerData();
            const prevShow = !!this.$el.find('.activity-offer-pending-update-banner').length;
            const nextShow = !!next.showPendingUpdateBanner;

            // Avoid full reRender on every pendingNotifyAt tick/fetch — only when
            // the banner appears/disappears or soft/hard copy changes.
            if (prevShow !== nextShow) {
                this.reRender();

                return;
            }

            if (nextShow) {
                this.$el.find('.activity-offer-pending-update-text').text(next.pendingUpdateBannerText || '');
                this.$el.find('.activity-offer-pending-update-timer').text(next.pendingUpdateCountdown || '');
                this.startPendingUpdateCountdown();
            }
        },

        /**
         * Re-fetch plan so status / pendingNotify* from AfterSave hooks show live.
         * Retries: silent second save (markPendingUpdate) can finish after the first response.
         */
        refreshOfferLifecycleState: function () {
            if (!this.model || !this.model.id) {
                return;
            }

            this.clearLifecycleFetchTimer();
            this.lifecycleFetchTimers = [];

            [150, 500, 1200].forEach(delay => {
                const timerId = window.setTimeout(() => {
                    if (!this.model || !this.model.id) {
                        return;
                    }

                    if (this.lifecycleFetching) {
                        return;
                    }

                    this.lifecycleFetching = true;

                    this.model.fetch()
                        .always(() => {
                            this.lifecycleFetching = false;
                        });
                }, delay);

                this.lifecycleFetchTimers.push(timerId);
            });
        },

        clearLifecycleFetchTimer: function () {
            if (this.lifecycleFetchTimerId) {
                window.clearTimeout(this.lifecycleFetchTimerId);
                this.lifecycleFetchTimerId = null;
            }

            if (this.lifecycleFetchTimers && this.lifecycleFetchTimers.length) {
                this.lifecycleFetchTimers.forEach(id => window.clearTimeout(id));
                this.lifecycleFetchTimers = [];
            }
        },

        startPendingUpdateCountdown: function () {
            this.clearPendingUpdateCountdown();

            if (this.model.get('status') !== 'Updated' || !this.model.get('pendingNotifyAt')) {
                return;
            }

            let fetchAttempts = 0;

            this.pendingUpdateTimerId = window.setInterval(() => {
                if (!this.isRendered()) {
                    this.clearPendingUpdateCountdown();

                    return;
                }

                if (this.model.get('status') !== 'Updated' || !this.model.get('pendingNotifyAt')) {
                    this.clearPendingUpdateCountdown();

                    return;
                }

                const $timer = this.$el.find('.activity-offer-pending-update-timer');

                if (!$timer.length) {
                    // Banner markup not ready yet — retry next tick, do not kill timer.
                    return;
                }

                const text = this.formatPendingUpdateCountdown();
                $timer.text(text);

                const soon = this.translate('pendingUpdateSendingSoon', 'messages', 'ActivityOffer');

                // After auto-send window, refresh model so banner/button clear.
                if (text === soon) {
                    fetchAttempts++;

                    if (fetchAttempts === 1 || fetchAttempts % 3 === 0) {
                        this.model.fetch();
                    }
                }
            }, 1000);
        },

        clearPendingUpdateCountdown: function () {
            if (this.pendingUpdateTimerId) {
                window.clearInterval(this.pendingUpdateTimerId);
                this.pendingUpdateTimerId = null;
            }
        },

        actionExtendPendingUpdate: function () {
            (new ShiftActions(this)).extendPendingUpdate();
        },

        actionSendPendingUpdate: function () {
            (new ShiftActions(this)).sendPendingUpdate();
        },

        createBottomView: function () {
            this.createPlanningView();

            const el = this.getSelector() || '#' + this.id;

            this.createView('bottom', this.bottomView, {
                model: this.model,
                scope: this.scope,
                fullSelector: el + ' .activity-offer-bottom-full',
                readOnly: this.readOnly,
                type: this.type,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this,
                portalLayoutDisabled: this.portalLayoutDisabled,
                isReturn: this.options.isReturn,
                dataObject: this.dataObject,
            });
        },

        createPlanningView: function () {
            const el = this.getSelector() || '#' + this.id;

            this.createView('planning', this.planningView, {
                model: this.model,
                scope: this.scope,
                fullSelector: el + ' .activity-offer-planning-top',
                readOnly: this.readOnly,
                type: this.type,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this,
                portalLayoutDisabled: this.portalLayoutDisabled,
                isReturn: this.options.isReturn,
                dataObject: this.dataObject,
            });
        },
    }));
});
