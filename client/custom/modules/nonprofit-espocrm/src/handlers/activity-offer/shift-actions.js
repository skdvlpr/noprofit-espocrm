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
            return ['Draft', 'CollectingAvailability'].includes(this.status()) && this.canEdit();
        }

        isAutoAssignVisible() {
            return ['CollectingAvailability', 'Planned'].includes(this.status()) && this.canEdit();
        }

        isConfirmPlanVisible() {
            return ['CollectingAvailability', 'Planned'].includes(this.status()) && this.canEdit();
        }

        isFillAvailabilityVisible() {
            return ['CollectingAvailability', 'Planned'].includes(this.status());
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
                        let msg = view
                            .translate('requestAvailabilitySuccess', 'messages', 'ActivityOffer')
                            .replace('{cohortCount}', String(response.cohortCount ?? 0))
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
                        } else {
                            Espo.Ui.success(msg);
                        }

                        this.refreshPlan(view);
                    })
                    .catch(() => {});
            });
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
                            .replace('{taskCount}', String(response.taskCount ?? 0))
                            .replace('{confirmedCount}', String(response.confirmedCount ?? 0))
                            .replace('{notifyCount}', String(response.notifyCount ?? 0));

                        Espo.Ui.success(msg);
                        this.refreshPlan(view);
                    })
                    .catch(() => {});
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
            view.model.trigger('update-related:coverage');
            view.model.trigger('update-related:slots');
            view.model.fetch();
        }
    }

    return Handler;
});
