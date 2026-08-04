define('nonprofit-espocrm:views/activity-offer/record/panels/coverage', ['views/record/panels/bottom'], function (Dep) {

    /**
     * Organizer coverage table: required / available / assigned per shift,
     * with a warning marker on uncovered shifts.
     */
    return Dep.extend({

        templateContent: `
            <div class="coverage-panel">
                {{#if hasData}}
                    <div class="list" style="overflow-x: auto;">
                        <table class="table table-condensed coverage-table" style="min-width: 920px;">
                            <thead>
                                <tr>
                                    <th style="white-space: nowrap;">{{shiftText}}</th>
                                    <th style="white-space: nowrap;">{{whenText}}</th>
                                    <th style="white-space: nowrap;">{{placeText}}</th>
                                    <th style="white-space: nowrap;">{{conditionsText}}</th>
                                    <th class="text-center" style="white-space: nowrap;">{{requiredText}}</th>
                                    <th class="text-center" style="white-space: nowrap;">{{availableText}}</th>
                                    <th class="text-center" style="white-space: nowrap;">{{assignedText}}</th>
                                    <th style="white-space: nowrap;">{{volunteersText}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{#each rows}}
                                    <tr>
                                        <td>{{name}}</td>
                                        <td class="text-muted" style="white-space: nowrap;">{{when}}</td>
                                        <td class="small">{{placeLabel}}</td>
                                        <td class="small">{{conditionsLabel}}</td>
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
                placeText: this.translate('place', 'fields', 'ActivityOfferSlot'),
                conditionsText: this.translate('conditions', 'fields', 'ActivityOfferSlot'),
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
            this.buttonList = [];

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
                            placeLabel: slot.placeLabel || '',
                            conditionsLabel: (slot.conditions || []).join('; '),
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
    });
});
