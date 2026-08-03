define('volunteer-activity-dispatch:handlers/activity-offer/shift-actions', [], function () {

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
                        const msg = view
                            .translate('requestAvailabilitySuccess', 'messages', 'ActivityOffer')
                            .replace('{notifyCount}', String(response.notifyCount ?? 0))
                            .replace('{slotCount}', String(response.slotCount ?? 0));

                        Espo.Ui.success(msg);
                        view.model.fetch();
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

                    view.model.fetch();
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
                        view.model.fetch();
                    })
                    .catch(() => {});
            });
        }

        fillAvailability() {
            const view = this.view;

            view.createView('availabilityModal',
                'volunteer-activity-dispatch:views/activity-offer/modals/availability',
                {id: view.model.id},
                modal => {
                    modal.render();

                    view.listenToOnce(modal, 'saved', () => {
                        view.model.fetch();
                    });
                }
            );
        }
    }

    return Handler;
});
