define('volunteer-activity-dispatch:views/task/record/panels/scheduler', ['views/record/panels/bottom'], function (Dep) {

    /**
     * Bottom panel: Meeting-style availability timeline for Task.
     * Attendee rows come from collaborators (+ assigned user via core scheduler logic).
     */
    return Dep.extend({

        templateContent: '<div class="scheduler-container no-margin">{{{scheduler}}}</div>',

        setup: function () {
            Dep.prototype.setup.call(this);

            const viewName = this.getMetadata().get(['clientDefs', this.scope, 'schedulerView']) ||
                'crm:views/scheduler/scheduler';

            this.createView('scheduler', viewName, {
                selector: '.scheduler-container',
                notToRender: true,
                model: this.model,
                usersField: 'collaborators',
                startField: 'dateStart',
                endField: 'dateEnd',
            });

            this.once('after:render', () => {
                if (this.disabled) {
                    return;
                }

                this.getView('scheduler').render();
                this.getView('scheduler').notToRender = false;
            });

            if (this.defs.disabled) {
                this.once('show', () => {
                    this.getView('scheduler').render();
                    this.getView('scheduler').notToRender = false;
                });
            }
        },

        actionRefresh: function () {
            this.getView('scheduler').reRender();
        },
    });
});
