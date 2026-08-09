define('nonprofit-espocrm:views/activity-offer/modals/request-availability-selected', [
    'views/modal',
], function (Dep) {

    /**
     * Organizer: pick one or more cohort volunteers and re-send the full
     * availability email pack in one batch (no invite wipe).
     */
    return Dep.extend({

        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: true,

        templateContent: `
            <div class="request-availability-selected">
                {{#if loading}}
                    <div class="text-muted">{{loadingText}}</div>
                {{else}}
                    {{#if empty}}
                        <div class="alert alert-warning">{{emptyText}}</div>
                    {{else}}
                        <p class="text-muted" style="margin-bottom: 0.8em;">{{hintText}}</p>
                        <div class="form-group" style="margin-bottom: 0.6em;">
                            <label class="control-label" style="display:flex; gap:0.5em; align-items:center; cursor:pointer;">
                                <input type="checkbox" data-name="selectAll"
                                    {{#if allSelected}}checked{{/if}}>
                                {{selectAllText}}
                            </label>
                        </div>
                        <div class="list-group" style="max-height: 22em; overflow: auto;">
                            {{#each volunteers}}
                                <label class="list-group-item"
                                    style="display:flex; gap:0.65em; align-items:flex-start; cursor:pointer; margin:0;">
                                    <input type="checkbox" data-user-id="{{id}}"
                                        {{#if selected}}checked{{/if}}
                                        style="margin-top:0.25em;">
                                    <span style="flex:1; min-width:0;">
                                        <strong>{{name}}</strong>
                                        {{#if responded}}
                                            <span class="label label-default" style="margin-left:0.35em;">{{respondedLabel}}</span>
                                        {{/if}}
                                        {{#if competenceText}}
                                            <div class="small text-muted">{{competenceText}}</div>
                                        {{/if}}
                                    </span>
                                </label>
                            {{/each}}
                        </div>
                    {{/if}}
                {{/if}}
            </div>
        `,

        data: function () {
            const selectedMap = this.selectedMap || {};
            const volunteers = (this.volunteers || []).map(row => {
                const labels = row.competenceLabels || [];

                return {
                    id: row.id,
                    name: row.name || row.id,
                    responded: !!row.responded,
                    respondedLabel: this.translate(
                        'volunteerStatsResponded',
                        'labels',
                        'ActivityOffer'
                    ),
                    competenceText: labels.length ? labels.join(', ') : '',
                    selected: !!selectedMap[row.id],
                };
            });

            const selectedCount = Object.keys(selectedMap).filter(id => selectedMap[id]).length;

            return {
                loading: !!this.loading,
                empty: !this.loading && volunteers.length === 0,
                loadingText: this.translate('Please wait...'),
                emptyText: this.translate(
                    'requestAvailabilitySelectedEmpty',
                    'messages',
                    'ActivityOffer'
                ),
                hintText: this.translate(
                    'requestAvailabilitySelectedHint',
                    'messages',
                    'ActivityOffer'
                ),
                selectAllText: this.translate('Select All'),
                allSelected: volunteers.length > 0 && selectedCount === volunteers.length,
                volunteers: volunteers,
            };
        },

        setup: function () {
            this.headerText = this.translate(
                'Request availability from selected',
                'labels',
                'ActivityOffer'
            );

            this.buttonList = [
                {
                    name: 'send',
                    label: this.translate('Send', 'labels'),
                    style: 'primary',
                    disabled: true,
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];

            this.loading = true;
            this.volunteers = [];
            this.selectedMap = {};

            this.wait(true);

            Espo.Ajax
                .getRequest('ActivityOffer/action/volunteerStats', {id: this.options.id})
                .then(response => {
                    this.volunteers = (response && response.volunteers) || [];
                    this.loading = false;
                    this.wait(false);
                    this.reRender();
                    this.syncSendButton();
                })
                .catch(() => {
                    this.loading = false;
                    this.wait(false);
                    this.reRender();
                });
        },

        afterRender: function () {
            this.$el.find('[data-name="selectAll"]').off('change.selectedPack').on('change.selectedPack', e => {
                const checked = !!e.currentTarget.checked;

                this.selectedMap = {};

                (this.volunteers || []).forEach(row => {
                    if (checked) {
                        this.selectedMap[row.id] = true;
                    }
                });

                this.reRender();
                this.syncSendButton();
            });

            this.$el.find('[data-user-id]').off('change.selectedPack').on('change.selectedPack', e => {
                const userId = e.currentTarget.getAttribute('data-user-id');

                if (!userId) {
                    return;
                }

                if (e.currentTarget.checked) {
                    this.selectedMap[userId] = true;
                }
                else {
                    delete this.selectedMap[userId];
                }

                this.syncSendButton();
                this.$el.find('[data-name="selectAll"]').prop(
                    'checked',
                    this.volunteers.length > 0 &&
                        Object.keys(this.selectedMap).length === this.volunteers.length
                );
            });

            this.syncSendButton();
        },

        syncSendButton: function () {
            const count = Object.keys(this.selectedMap || {}).length;

            if (count === 0) {
                this.disableButton('send');
            }
            else {
                this.enableButton('send');
            }
        },

        actionSend: function () {
            const userIds = Object.keys(this.selectedMap || {}).filter(id => this.selectedMap[id]);

            if (!userIds.length) {
                return;
            }

            this.disableButton('send');
            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('ActivityOffer/action/requestAvailabilityForUsers', {
                    id: this.options.id,
                    userIds: userIds,
                })
                .then(response => {
                    this.trigger('sent', response);
                    this.close();
                })
                .catch(() => {
                    this.enableButton('send');
                });
        },
    });
});
