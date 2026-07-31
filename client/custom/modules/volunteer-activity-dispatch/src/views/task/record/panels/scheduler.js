define('volunteer-activity-dispatch:views/task/record/panels/scheduler', ['views/record/panels/bottom'], function (Dep) {

    /**
     * Bottom panel: Meeting-style availability timeline for Task.
     * Attendee rows = collaborators (+ assigned user).
     * Needs dateStart + dateEnd; otherwise shows a clear hint (not blank "No Data" only).
     */
    return Dep.extend({

        templateContent: `
            <div class="scheduler-hint text-muted small" style="margin-bottom:0.5em;display:none;"></div>
            <div class="scheduler-container no-margin">{{{scheduler}}}</div>
        `,

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

            this.listenTo(this.model, 'change:dateStart change:dateEnd change:collaboratorsIds change:assignedUserId', () => {
                this.updateHint();
            });

            this.once('after:render', () => {
                if (this.disabled) {
                    return;
                }

                this.updateHint();
                this.getView('scheduler').render();
                this.getView('scheduler').notToRender = false;
            });

            if (this.defs.disabled) {
                this.once('show', () => {
                    this.updateHint();
                    this.getView('scheduler').render();
                    this.getView('scheduler').notToRender = false;
                });
            }
        },

        updateHint: function () {
            const $hint = this.$el.find('.scheduler-hint');

            if (!$hint.length) {
                return;
            }

            const hasStart = !!this.model.get('dateStart') || !!this.model.get('dateStartDate');
            const hasEnd = !!this.model.get('dateEnd') || !!this.model.get('dateEndDate');
            const hasPeople = (this.model.get('collaboratorsIds') || []).length > 0 ||
                !!this.model.get('assignedUserId');

            if (hasStart && hasEnd && hasPeople) {
                $hint.hide().text('');
                return;
            }

            const msg = this.translate('schedulerNeedDatesAndPeople', 'messages', 'Task') ||
                'Set start/end date-time and at least assigned user or collaborators to show the planner.';

            $hint.text(msg).show();
        },

        actionRefresh: function () {
            this.updateHint();
            this.getView('scheduler').reRender();
        },
    });
});
