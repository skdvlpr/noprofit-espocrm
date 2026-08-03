define('volunteer-activity-dispatch:views/activity-offer/record/panels/coverage', ['views/record/panels/bottom'], function (Dep) {

    /**
     * Organizer coverage table: required / available / assigned per shift,
     * with a warning marker on uncovered shifts. Also hosts the volunteer
     * "Fill availability" button.
     */
    return Dep.extend({

        templateContent: `
            <div class="coverage-panel">
                {{#if hasData}}
                    <div class="list" style="overflow-x: auto;">
                        <table class="table table-condensed" style="min-width: 640px;">
                            <thead>
                                <tr>
                                    <th>{{shiftText}}</th>
                                    <th>{{whenText}}</th>
                                    <th class="text-center" style="width: 8%;">{{requiredText}}</th>
                                    <th class="text-center" style="width: 8%;">{{availableText}}</th>
                                    <th class="text-center" style="width: 8%;">{{assignedText}}</th>
                                    <th style="width: 28%;">{{volunteersText}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{#each rows}}
                                    <tr>
                                        <td>{{name}}</td>
                                        <td class="text-muted">{{when}}</td>
                                        <td class="text-center">{{requiredCount}}</td>
                                        <td class="text-center">{{availableCount}}</td>
                                        <td class="text-center">
                                            {{#if isCovered}}
                                                <span class="label label-success">{{assignedCount}}</span>
                                            {{else}}
                                                <span class="label label-danger" title="{{../uncoveredText}}">{{assignedCount}} ⚠</span>
                                            {{/if}}
                                        </td>
                                        <td class="small">{{assignedNames}}</td>
                                    </tr>
                                {{/each}}
                            </tbody>
                        </table>
                    </div>
                {{else}}
                    <div class="text-muted">{{noDataText}}</div>
                {{/if}}
            </div>
        `,

        data: function () {
            return {
                hasData: this.rows.length > 0,
                rows: this.rows,
                shiftText: this.translate('ActivityOfferSlot', 'scopeNames'),
                whenText: this.translate('dateStart', 'fields', 'ActivityOfferSlot'),
                requiredText: this.translate('requiredCount', 'fields', 'ActivityOfferSlot'),
                availableText: this.translate('coverageAvailable', 'labels', 'ActivityOffer'),
                assignedText: this.translate('coverageAssigned', 'labels', 'ActivityOffer'),
                volunteersText: this.translate('coverageVolunteers', 'labels', 'ActivityOffer'),
                uncoveredText: this.translate('coverageUncovered', 'labels', 'ActivityOffer'),
                noDataText: this.translate('No Data'),
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.rows = [];

            this.buttonList = [
                {
                    action: 'fillAvailability',
                    label: this.translate('Fill availability', 'labels', 'ActivityOffer'),
                    style: 'primary',
                    title: this.translate('Fill availability', 'labels', 'ActivityOffer'),
                },
            ];

            this.listenTo(this.model, 'sync', () => this.fetchCoverage());

            this.fetchCoverage();
        },

        fetchCoverage: function () {
            if (!this.model.id) {
                return;
            }

            Espo.Ajax
                .getRequest('ActivityOffer/action/coverage', {id: this.model.id})
                .then(data => {
                    this.rows = (data.slots || []).map(slot => {
                        return {
                            name: slot.name || slot.categoryLabel,
                            when: this.formatWhen(slot.dateStart, slot.dateEnd),
                            requiredCount: slot.requiredCount,
                            availableCount: slot.availableCount,
                            assignedCount: slot.assignedCount,
                            isCovered: slot.isCovered,
                            assignedNames: (slot.assigned || [])
                                .map(u => u.name)
                                .join(', '),
                        };
                    });

                    this.reRender();
                })
                .catch(() => {});
        },

        formatWhen: function (dateStart, dateEnd) {
            if (!dateStart) {
                return '';
            }

            const m = this.getDateTime().toMoment(dateStart);
            let result = m.format('ddd D MMM ' + this.getDateTime().timeFormat);

            if (dateEnd) {
                result += ' – ' + this.getDateTime().toMoment(dateEnd)
                    .format(this.getDateTime().timeFormat);
            }

            return result;
        },

        actionFillAvailability: function () {
            this.createView('availabilityModal',
                'volunteer-activity-dispatch:views/activity-offer/modals/availability',
                {id: this.model.id},
                view => {
                    view.render();

                    this.listenToOnce(view, 'saved', () => {
                        this.fetchCoverage();
                        this.model.fetch();
                    });
                }
            );
        },
    });
});
