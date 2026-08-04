define('nonprofit-espocrm:views/activity-offer/record/panels/tasks', [
    'views/record/panels/relationship',
], function (Dep) {

    /**
     * Allow “+” even though Task.activityOffer is readOnly on the Task form
     * (set by confirm / parent relate; not editable by volunteers).
     */
    return Dep.extend({

        setupCreateAvailability: function () {
            // Keep defs.create from relationshipPanels / defaults.
        },
    });
});
