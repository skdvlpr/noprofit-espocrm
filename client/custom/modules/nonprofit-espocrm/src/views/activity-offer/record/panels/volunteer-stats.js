define('nonprofit-espocrm:views/activity-offer/record/panels/volunteer-stats', [
    'views/record/panels/bottom',
], function (Dep) {

    /**
     * Cohort analysis: competenze match vs responses / assignments.
     */
    return Dep.extend({

        templateContent: `
            <div class="volunteer-stats-panel">
                {{#if hasSummary}}
                    <div class="volunteer-stats-summary row">
                        {{#each summaryCards}}
                            <div class="col-sm-4 col-xs-6">
                                <div class="volunteer-stats-card">
                                    <div class="volunteer-stats-card-value {{className}}">{{value}}</div>
                                    <div class="volunteer-stats-card-label">{{label}}</div>
                                </div>
                            </div>
                        {{/each}}
                    </div>
                {{/if}}
                {{#if hasData}}
                    <div class="list activity-offer-panel-scroll">
                        <table class="table table-condensed volunteer-stats-table">
                            <thead>
                                <tr>
                                    <th>{{volunteerText}}</th>
                                    <th>{{competencesText}}</th>
                                    <th>{{eligibleText}}</th>
                                    <th class="text-center">{{respondedText}}</th>
                                    <th class="text-center">{{availableText}}</th>
                                    <th class="text-center">{{assignedText}}</th>
                                    <th class="text-center">{{gapsText}}</th>
                                    <th>{{matchText}}</th>
                                </tr>
                            </thead>
                            <tbody>
                                {{#each rows}}
                                    <tr>
                                        <td>
                                            {{#if userId}}
                                                <a href="#User/view/{{userId}}" class="link">{{name}}</a>
                                            {{else}}
                                                {{name}}
                                            {{/if}}
                                        </td>
                                        <td class="small">{{competencesLabel}}</td>
                                        <td class="small" title="{{eligibleNames}}">{{eligibleLabel}}</td>
                                        <td class="text-center">
                                            {{#if responded}}
                                                <span class="label label-success">{{respondedYes}}</span>
                                            {{else}}
                                                <span class="label label-default">{{respondedNo}}</span>
                                            {{/if}}
                                        </td>
                                        <td class="text-center">{{availableCount}}</td>
                                        <td class="text-center">
                                            {{#if assignedCount}}
                                                <span class="label label-success">{{assignedCount}}</span>
                                            {{else}}
                                                0
                                            {{/if}}
                                        </td>
                                        <td class="text-center">
                                            {{#if fillableGaps}}
                                                <span class="label label-default">{{fillableGaps}}</span>
                                            {{else}}
                                                0
                                            {{/if}}
                                        </td>
                                        <td class="small text-muted">{{matchLabel}}</td>
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
            const yes = this.translate('Yes');
            const no = this.translate('No');

            return {
                hasData: this.rows.length > 0,
                hasSummary: this.summaryCards.length > 0,
                summaryCards: this.summaryCards,
                rows: this.rows.map(row => Object.assign({}, row, {
                    respondedYes: yes,
                    respondedNo: no,
                })),
                volunteerText: this.translate('volunteerStatsVolunteer', 'labels', 'ActivityOffer'),
                competencesText: this.translate('volunteerStatsCompetences', 'labels', 'ActivityOffer'),
                eligibleText: this.translate('volunteerStatsEligible', 'labels', 'ActivityOffer'),
                respondedText: this.translate('volunteerStatsResponded', 'labels', 'ActivityOffer'),
                availableText: this.translate('coverageAvailable', 'labels', 'ActivityOffer'),
                assignedText: this.translate('coverageAssigned', 'labels', 'ActivityOffer'),
                gapsText: this.translate('volunteerStatsGaps', 'labels', 'ActivityOffer'),
                matchText: this.translate('volunteerStatsMatch', 'labels', 'ActivityOffer'),
                noDataText: this.translate('No Data'),
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.rows = [];
            this.summaryCards = [];
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

            this.fetchStats();
        },

        scheduleFetch: function () {
            if (this._fetchTimer) {
                clearTimeout(this._fetchTimer);
            }

            this._fetchTimer = setTimeout(() => {
                this._fetchTimer = null;
                this.fetchStats();
            }, 120);
        },

        fetchStats: function () {
            if (!this.model.id) {
                return;
            }

            Espo.Ajax
                .getRequest('ActivityOffer/action/volunteerStats', {id: this.model.id})
                .then(data => {
                    const summary = data.summary || {};
                    const cohortSize = summary.cohortSize || 0;
                    const respondedCount = summary.respondedCount || 0;
                    const slotCount = summary.slotCount || 0;
                    const coveredSlots = summary.coveredSlots != null
                        ? summary.coveredSlots
                        : Math.max(0, slotCount - (summary.uncoveredSlots || 0));

                    // Cohort size alone is redundant with Hanno risposto "N / total".
                    this.summaryCards = [
                        {
                            value: respondedCount + ' / ' + cohortSize,
                            label: this.translate('volunteerStatsResponded', 'labels', 'ActivityOffer'),
                            className: respondedCount ? 'text-success' : '',
                        },
                        {
                            value: summary.assignedPeople || 0,
                            label: this.translate('volunteerStatsAssignedPeople', 'labels', 'ActivityOffer'),
                            className: summary.assignedPeople ? 'text-success' : '',
                        },
                        {
                            value: coveredSlots + ' / ' + slotCount,
                            label: this.translate('volunteerStatsCoveredSlots', 'labels', 'ActivityOffer'),
                            className: (summary.uncoveredSlots || 0) ? 'text-warning' : 'text-success',
                        },
                    ];

                    this.rows = (data.volunteers || []).map(row => {
                        const eligibleNames = (row.eligibleSlots || []).map(s => s.name).join(', ');
                        const eligibleCount = (row.eligibleSlots || []).length;

                        return {
                            userId: row.id,
                            name: row.name,
                            competencesLabel: (row.competenceLabels || []).join(', ') || '—',
                            eligibleLabel: eligibleCount
                                ? String(eligibleCount)
                                : '0',
                            eligibleNames: eligibleNames,
                            responded: !!row.responded,
                            availableCount: row.availableCount || 0,
                            assignedCount: row.assignedCount || 0,
                            fillableGaps: row.fillableGaps || 0,
                            matchLabel: row.matchLabel || '',
                        };
                    });

                    this.reRender();
                })
                .catch(() => {});
        },
    });
});
