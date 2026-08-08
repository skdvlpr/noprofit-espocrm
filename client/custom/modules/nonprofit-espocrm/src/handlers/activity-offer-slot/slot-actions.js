define('nonprofit-espocrm:handlers/activity-offer-slot/slot-actions', [], function () {

    /**
     * Detail actions for a single shift (ActivityOfferSlot).
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

        isCancelSlotVisible() {
            return ['Published', 'Covered'].includes(this.status()) && this.canEdit();
        }

        cancelSlot() {
            const view = this.view;

            Espo.Ui.confirm(
                view.translate('cancelSlotConfirm', 'messages', 'ActivityOfferSlot'),
                {
                    confirmText: view.translate('Cancel shift', 'labels', 'ActivityOfferSlot'),
                    cancelText: view.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify('...');

                Espo.Ajax
                    .postRequest('ActivityOfferSlot/action/cancel', {id: view.model.id})
                    .then(response => {
                        const msg = view
                            .translate('cancelSlotSuccess', 'messages', 'ActivityOfferSlot')
                            .replace('{notifyCount}', String(response.notifyCount ?? 0))
                            .replace('{emailCount}', String(response.emailCount ?? 0));

                        Espo.Ui.success(msg);
                        view.model.fetch();

                        if (response.planClosed) {
                            const planStatus = response.planStatus || 'Closed';
                            const statusLabel = view.getLanguage()
                                .translateOption(planStatus, 'status', 'ActivityOffer');

                            Espo.Ui.notify(
                                view.translate('cancelSlotPlanClosed', 'messages', 'ActivityOfferSlot')
                                    .replace('{status}', statusLabel)
                            );
                        }
                    })
                    .catch(() => {});
            });
        }
    }

    return Handler;
});
