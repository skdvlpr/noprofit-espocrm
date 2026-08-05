define('nonprofit-espocrm:views/activity-offer/record/panels/coverage', ['views/record/panels/bottom'], function (Dep) {

    /**
     * Organizer coverage table — staffing-focused columns only.
     */
    return Dep.extend({

        templateContent: `
            <div class="coverage-panel">
                {{#if hasData}}
                    <div class="list activity-offer-panel-scroll">
                        <table class="table table-condensed coverage-table">
                            <thead>
                                <tr>
                                    <th>{{shiftText}}</th>
                                    <th>{{whenText}}</th>
                                    <th class="text-center">{{requiredText}}</th>
                                    <th class="text-center">{{availableText}}</th>
                                    <th class="text-center">{{assignedText}}</th>
                                    <th>{{volunteersText}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{#each rows}}
                                    <tr class="{{#unless isCovered}}coverage-row-uncovered{{/unless}}">
                                        <td>
                                            <div class="coverage-shift-name">{{name}}</div>
                                            {{#if conditionsLabel}}
                                                <div class="text-muted small coverage-shift-meta"
                                                     title="{{conditionsLabel}}">{{conditionsLabel}}</div>
                                            {{/if}}
                                        </td>
                                        <td class="text-muted" style="white-space: nowrap;">{{when}}</td>
                                        <td class="text-center">{{requiredCount}}</td>
                                        <td class="text-center">{{availableCount}}</td>
                                        <td class="text-center">
                                            {{#if isCovered}}
                                                <span class="label label-success">{{assignedCount}}</span>
                                            {{else}}
                                                <span class="label label-danger" title="{{../uncoveredText}}">
                                                    {{assignedCount}} ⚠
                                                </span>
                                            {{/if}}
                                        </td>
                                        <td class="small">{{assignedNames}}</td>
                                    </tr>
                                {{/each}}
                            </tbody>
                        </table>
                    </div>
                    {{#if uncoveredCount}}
                        <div class="text-danger small margin-top">
                            {{uncoveredSummary}}
                        </div>
                    {{/if}}
                {{else}}
                    <div class="text-muted">{{noDataText}}</div>
                {{/if}}
            </div>
        `,

        data: function () {
            return {
                hasData: this.rows.length > 0,
                rows: this.rows,
                uncoveredCount: this.uncoveredCount,
                uncoveredSummary: this.translate('coverageUncoveredSummary', 'messages', 'ActivityOffer')
                    .replace('{count}', String(this.uncoveredCount)),
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
            this.uncoveredCount = 0;
            this.buttonList = [];
            this._fetchTimer = null;

            this.listenTo(this.model, 'sync', () => this.scheduleFetch());
            this.listenTo(
                this.model,
                'change:contactsIds change:teamsIds change:status',
                () => this.scheduleFetch()
            );
            this.listenTo(
                this.model,
                [
                    'update-related:slots',
                    'update-related:tasks',
                    'update-related:coverage',
                    'update-related:invites',
                    'after:relate',
                    'after:unrelate',
                    'after:related-change:slots',
                    'after:related-change:tasks',
                    'after:related-change:invites',
                    'update-all',
                ].join(' '),
                () => this.scheduleFetch()
            );

            this.fetchCoverage();
        },

        scheduleFetch: function () {
            if (this._fetchTimer) {
                clearTimeout(this._fetchTimer);
            }

            this._fetchTimer = setTimeout(() => {
                this._fetchTimer = null;
                this.fetchCoverage();
            }, 120);
        },

        fetchCoverage: function () {
            if (!this.model.id) {
                return;
            }

            Espo.Ajax
                .getRequest('ActivityOffer/action/coverage', {id: this.model.id})
                .then(data => {
                    this.uncoveredCount = data.uncoveredCount || 0;
                    this.rows = (data.slots || []).map(slot => {
                        const conditions = (slot.conditions || []).join('; ');

                        return {
                            name: slot.categoryLabel || slot.name,
                            when: this.formatWhen(slot.dateStart, slot.dateEnd),
                            conditionsLabel: conditions,
                            requiredCount: slot.requiredCount,
                            availableCount: slot.availableCount,
                            assignedCount: slot.assignedCount,
                            isCovered: slot.isCovered,
                            assignedNames: (slot.assigned || [])
                                .map(u => u.name)
                                .join(', ') || '—',
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
