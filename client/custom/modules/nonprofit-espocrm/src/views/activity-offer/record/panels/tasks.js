define('nonprofit-espocrm:views/activity-offer/record/panels/tasks', [
    'views/record/panels/relationship',
], function (Dep) {

    /**
     * Allow “+” even though Task.activityOffer is readOnly on the Task form
     * (set by confirm / parent relate; not editable by volunteers).
     */
    return Dep.extend({

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

                this.model.trigger('update-related:coverage');
            });
        },

        setupCreateAvailability: function () {
            // Keep defs.create from relationshipPanels / defaults.
        },
    });
});
