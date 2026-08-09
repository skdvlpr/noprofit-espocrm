define('nonprofit-espocrm:views/activity-offer/record/panels/slots', [
    'views/record/panels/relationship',
], function (Dep) {

    return Dep.extend({

        /**
         * After relationship list is ready: keep Coverage / Match in sync when
         * related shifts are created, edited, or removed (Espo fires
         * after:related-change on collection change; collection sync covers
         * create/delete refreshes).
         */
        setupLast: function () {
            Dep.prototype.setupLast.call(this);

            if (!this.collection) {
                return;
            }

            this._planningSyncSeen = false;

            this.listenTo(this.collection, 'sync', () => {
                if (!this._planningSyncSeen) {
                    this._planningSyncSeen = true;

                    return;
                }

                // Skip echo when this sync came from live-refresh fetch below.
                if (this._slotsLiveFetching) {
                    return;
                }

                this.model.trigger('update-related:coverage');
                // Parent plan may flip to Updated + pendingNotify* via slot AfterSave.
                this.model.trigger('update-related:slots');
            });

            // Inline edit / related modal save (collection may not re-sync).
            this.listenTo(this.collection, 'change', () => {
                if (this._slotsLiveFetching) {
                    return;
                }

                this.model.trigger('update-related:coverage');
                this.model.trigger('update-related:slots');
            });

            this.listenTo(this.model, 'after:relate after:unrelate', () => {
                this.model.trigger('update-related:coverage');
                this.model.trigger('update-related:slots');
            });

            // Live planner refresh: re-fetch Turni rows so available/assigned counts update.
            this.listenTo(this.model, 'update-related:coverage', () => {
                if (!this.collection || this._slotsLiveFetching) {
                    return;
                }

                this._slotsLiveFetching = true;
                this.collection.fetch()
                    .finally(() => {
                        this._slotsLiveFetching = false;
                    });
            });
        },

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
                    this.model.trigger('update-related:coverage');
                });
            });
        },
    });
});
