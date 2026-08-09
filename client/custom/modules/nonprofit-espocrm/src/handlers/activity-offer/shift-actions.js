define('nonprofit-espocrm:handlers/activity-offer/shift-actions', [], function () {

    /**
     * Detail actions for the weekly shift plan lifecycle:
     * request availability -> auto-assign -> confirm.
     * Plus the volunteer-facing "fill availability" action.
     */
    class Handler {
        constructor(view) {
            this.view = view;
        }

        status() {
            return this.view.model ? this.view.model.get('status') : null;
        }

        canEdit() {
            return this.view.model &&
                this.view.getAcl().check(this.view.model, 'edit');
        }

        isRequestAvailabilityVisible() {
            const status = this.status();

            // Re-request anytime on open plans (including after discard → Confirmed).
            return !!status
                && !['Closed', 'Completed'].includes(status)
                && this.canEdit();
        }

        isRequestAvailabilitySelectedVisible() {
            const status = this.status();

            // Selective pack resend after the plan is already open (not Draft).
            return ['CollectingAvailability', 'Planned', 'Confirmed', 'Updated'].includes(status)
                && this.canEdit();
        }

        isAutoAssignVisible() {
            return ['CollectingAvailability', 'Planned'].includes(this.status()) && this.canEdit();
        }

        isConfirmPlanVisible() {
            return ['CollectingAvailability', 'Planned'].includes(this.status()) && this.canEdit();
        }

        isClosePlanVisible() {
            const status = this.status();

            return status
                && status !== 'Draft'
                && status !== 'Closed'
                && status !== 'Completed'
                && this.canEdit();
        }

        isCancelAllVisible() {
            const status = this.status();

            return status
                && status !== 'Draft'
                && status !== 'Closed'
                && status !== 'Completed'
                && this.canEdit();
        }

        isSendPendingUpdateVisible() {
            return this.status() === 'Updated' && this.canEdit() &&
                !!this.view.model.get('pendingNotifyKind');
        }

        isFillAvailabilityVisible() {
            return ['CollectingAvailability', 'Planned'].includes(this.status());
        }

        extendPendingUpdate() {
            const view = this.view;

            if (!this.isSendPendingUpdateVisible()) {
                return;
            }

            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('ActivityOffer/action/extendPendingUpdate', {
                    id: view.model.id,
                    minutes: 5,
                })
                .then(response => {
                    const minutes = response.extendedMinutes ?? 5;
                    const msg = view
                        .translate('extendPendingUpdateSuccess', 'messages', 'ActivityOffer')
                        .replace('{minutes}', String(minutes));

                    Espo.Ui.success(msg);
                    this.refreshPlan(view);
                })
                .catch(() => {});
        }

        discardPendingUpdate() {
            const view = this.view;

            if (!this.isSendPendingUpdateVisible()) {
                return;
            }

            Espo.Ui.confirm(
                view.translate('discardPendingUpdateConfirm', 'messages', 'ActivityOffer'),
                {
                    confirmText: view.translate('Do not send update', 'labels', 'ActivityOffer'),
                    cancelText: view.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOffer/action/discardPendingUpdate', {
                        id: view.model.id,
                    })
                    .then(() => {
                        Espo.Ui.success(
                            view.translate('discardPendingUpdateSuccess', 'messages', 'ActivityOffer')
                        );
                        this.refreshPlan(view);
                    })
                    .catch(() => {});
            });
        }

        requestAvailability() {
            const view = this.view;

            Espo.Ui.confirm(
                view.translate('requestAvailabilityConfirm', 'messages', 'ActivityOffer'),
                {
                    confirmText: view.translate('Request availability', 'labels', 'ActivityOffer'),
                    cancelText: view.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOffer/action/requestAvailability', {id: view.model.id})
                    .then(response => {
                        this.showAvailabilityRequestResult(view, response, false);
                        this.refreshPlan(view);
                    })
                    .catch(() => {});
            });
        }

        requestAvailabilitySelected() {
            const view = this.view;

            if (!this.isRequestAvailabilitySelectedVisible()) {
                return;
            }

            view.createView(
                'requestAvailabilitySelectedModal',
                'nonprofit-espocrm:views/activity-offer/modals/request-availability-selected',
                {id: view.model.id},
                modal => {
                    modal.render();

                    view.listenToOnce(modal, 'sent', response => {
                        this.showAvailabilityRequestResult(view, response, true);
                        this.refreshPlan(view);
                    });
                }
            );
        }

        showAvailabilityRequestResult(view, response, selectedOnly) {
            const successKey = selectedOnly
                ? 'requestAvailabilitySelectedSuccess'
                : 'requestAvailabilitySuccess';

            let msg = view
                .translate(successKey, 'messages', 'ActivityOffer')
                .replace('{cohortCount}', String(response.cohortCount ?? response.userCount ?? 0))
                .replace('{userCount}', String(response.userCount ?? response.cohortCount ?? 0))
                .replace('{emailCount}', String(response.emailCount ?? 0))
                .replace('{notifyCount}', String(response.notifyCount ?? 0))
                .replace('{slotCount}', String(response.slotCount ?? 0));

            const failed = response.emailFailed || [];
            const skipped = response.emailSkipped || [];

            if (failed.length || skipped.length) {
                const parts = [];

                if (failed.length) {
                    parts.push(
                        view.translate('requestAvailabilityEmailFailed', 'messages', 'ActivityOffer')
                            .replace('{list}', failed.join('; '))
                    );
                }

                if (skipped.length) {
                    parts.push(
                        view.translate('requestAvailabilityEmailSkipped', 'messages', 'ActivityOffer')
                            .replace('{list}', skipped.join('; '))
                    );
                }

                Espo.Ui.warning(msg + '\n' + parts.join('\n'));
            }
            else {
                Espo.Ui.success(msg);
            }
        }

        autoAssign() {
            const view = this.view;

            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('ActivityOffer/action/autoAssign', {id: view.model.id})
                .then(response => {
                    const uncovered = response.uncovered || [];

                    let msg = view
                        .translate('autoAssignSuccess', 'messages', 'ActivityOffer')
                        .replace('{assignedCount}', String(response.assignedCount ?? 0));

                    if (uncovered.length) {
                        msg += '\n' + view
                            .translate('autoAssignUncovered', 'messages', 'ActivityOffer')
                            .replace('{list}', uncovered.join(', '));

                        Espo.Ui.warning(msg);
                    } else {
                        Espo.Ui.success(msg);
                    }

                    this.refreshPlan(view);
                })
                .catch(() => {});
        }

        confirmPlan() {
            const view = this.view;

            Espo.Ui.confirm(
                view.translate('confirmPlanConfirm', 'messages', 'ActivityOffer'),
                {
                    confirmText: view.translate('Confirm plan', 'labels', 'ActivityOffer'),
                    cancelText: view.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOffer/action/confirmPlan', {id: view.model.id})
                    .then(response => {
                        const msg = view
                            .translate('confirmPlanSuccess', 'messages', 'ActivityOffer')
                            .replace('{confirmedCount}', String(response.confirmedCount ?? 0))
                            .replace('{notifyCount}', String(response.notifyCount ?? 0));

                        Espo.Ui.success(msg);
                        this.refreshPlan(view);
                    })
                    .catch(() => {});
            });
        }

        closePlan() {
            const view = this.view;

            Espo.Ui.confirm(
                view.translate('closePlanConfirm', 'messages', 'ActivityOffer'),
                {
                    confirmText: view.translate('Close plan', 'labels', 'ActivityOffer'),
                    cancelText: view.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOffer/action/closePlan', {id: view.model.id})
                    .then(() => {
                        Espo.Ui.success(view.translate('closePlanSuccess', 'messages', 'ActivityOffer'));
                        this.refreshPlan(view);
                    })
                    .catch(() => {});
            });
        }

        cancelAll() {
            const view = this.view;

            Espo.Ui.confirm(
                view.translate('cancelAllConfirm', 'messages', 'ActivityOffer'),
                {
                    confirmText: view.translate('Cancel all', 'labels', 'ActivityOffer'),
                    cancelText: view.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOffer/action/cancelAll', {id: view.model.id})
                    .then(response => {
                        const msg = view
                            .translate('cancelAllSuccess', 'messages', 'ActivityOffer')
                            .replace('{cancelledCount}', String(response.cancelledCount ?? 0))
                            .replace('{notifyCount}', String(response.notifyCount ?? 0))
                            .replace('{emailCount}', String(response.emailCount ?? 0));

                        Espo.Ui.success(msg);
                        this.refreshPlan(view);
                    })
                    .catch(() => {});
            });
        }

        sendPendingUpdate() {
            const view = this.view;

            if (!view.model || !view.model.id) {
                return;
            }

            // Stale UI: banner visible but server already finalized.
            if (view.model.get('status') !== 'Updated' || !view.model.get('pendingNotifyKind')) {
                this.refreshPlan(view);

                return;
            }

            Espo.Ui.confirm(
                view.translate('sendPendingUpdateConfirm', 'messages', 'ActivityOffer'),
                {
                    confirmText: view.translate('Send update now', 'labels', 'ActivityOffer'),
                    cancelText: view.translate('Cancel'),
                    confirmStyle: 'warning',
                    backdrop: true,
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOffer/action/sendPendingUpdate', {id: view.model.id})
                    .then(response => {
                        if (response.alreadySent) {
                            Espo.Ui.success(
                                view.translate('sendPendingUpdateSoftSuccess', 'messages', 'ActivityOffer')
                            );
                            this.refreshPlan(view);

                            return;
                        }

                        const kind = response.kind || '';
                        const key = kind === 'hard'
                            ? 'sendPendingUpdateHardSuccess'
                            : 'sendPendingUpdateSoftSuccess';

                        Espo.Ui.success(view.translate(key, 'messages', 'ActivityOffer'));
                        this.refreshPlan(view);
                    })
                    .catch(xhr => {
                        const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message) ||
                            view.translate('Error occurred', 'messages');

                        Espo.Ui.error(msg);
                        this.refreshPlan(view);
                    });
            });
        }

        fillAvailability() {
            const view = this.view;

            view.createView('availabilityModal',
                'nonprofit-espocrm:views/activity-offer/modals/availability',
                {id: view.model.id},
                modal => {
                    modal.render();

                    view.listenToOnce(modal, 'saved', () => {
                        this.refreshPlan(view);
                    });
                }
            );
        }

        refreshPlan(view) {
            if (!view || !view.model) {
                return;
            }

            view.model.trigger('update-related:coverage');
            view.model.trigger('update-related:slots');

            view.model.fetch().then(() => {
                if (typeof view.updatePendingUpdateBanner === 'function') {
                    view.updatePendingUpdateBanner();
                }

                if (typeof view.startPendingUpdateCountdown === 'function') {
                    view.startPendingUpdateCountdown();
                }
            });
        }
    }

    return Handler;
});
