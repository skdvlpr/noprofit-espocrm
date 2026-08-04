define('nonprofit-espocrm:views/activity-offer/record/panels/slots', [
    'views/record/panels/relationship',
], function (Dep) {

    return Dep.extend({

        /**
         * “+” opens the WhatsApp-style mass-create modal instead of
         * the single-record create form.
         */
        actionCreateRelated: function () {
            const status = this.model.get('status');

            if (!['Draft', 'CollectingAvailability'].includes(status)) {
                Espo.Ui.warning(
                    this.translate('addShiftsOnlyDraft', 'messages', 'ActivityOffer')
                );

                return;
            }

            if (!this.getAcl().check('ActivityOfferSlot', 'create') &&
                !this.getAcl().checkModel(this.model, 'edit')
            ) {
                Espo.Ui.error(this.translate('Access denied'));

                return;
            }

            this.createView('addWeekSlotsModal', 'nonprofit-espocrm:views/activity-offer/modals/create-week-slots', {
                model: this.model,
            }, view => {
                view.render();

                this.listenToOnce(view, 'after:save', () => {
                    this.collection.fetch();
                    this.model.trigger('update-related:slots');
                    this.model.trigger('after:relate');
                    // Refresh coverage panel if present.
                    this.model.trigger('update-related:coverage');
                });
            });
        },
    });
});
