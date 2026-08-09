define('nonprofit-espocrm:views/activity-offer/modals/request-availability-selected', [
    'views/modal',
], function (Dep) {

    /**
     * Organizer: multi-select volunteers × shifts, then one pack send
     * (email + in-app). No invite wipe.
     */
    return Dep.extend({

        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: true,
        cssName: 'dialog-record',

        templateContent: `
            <div class="sh-avail-pack">
                {{#if loading}}
                    <div class="text-muted">{{loadingText}}</div>
                {{else}}
                    {{#if empty}}
                        <div class="alert alert-warning">{{emptyText}}</div>
                    {{else}}
                        <p class="sh-avail-pack__hint text-muted">{{hintText}}</p>
                        <div class="sh-avail-pack__grid">
                            <section class="sh-avail-pack__col">
                                <header class="sh-avail-pack__col-head">
                                    <h5>{{peopleTitle}}</h5>
                                    <label class="sh-avail-pack__select-all">
                                        <input type="checkbox" data-name="selectAllUsers"
                                            {{#if allUsersSelected}}checked{{/if}}>
                                        <span>{{selectAllText}}</span>
                                    </label>
                                </header>
                                <div class="sh-avail-pack__list">
                                    {{#each volunteers}}
                                        <label class="sh-avail-pack__row">
                                            <input type="checkbox" data-user-id="{{id}}"
                                                {{#if selected}}checked{{/if}}>
                                            <span class="sh-avail-pack__row-body">
                                                <span class="sh-avail-pack__row-title">
                                                    <strong>{{name}}</strong>
                                                    {{#if responded}}
                                                        <span class="label label-success">{{respondedLabel}}</span>
                                                    {{/if}}
                                                </span>
                                                {{#if competenceText}}
                                                    <span class="sh-avail-pack__row-meta text-muted">{{competenceText}}</span>
                                                {{/if}}
                                            </span>
                                        </label>
                                    {{/each}}
                                </div>
                            </section>
                            <section class="sh-avail-pack__col">
                                <header class="sh-avail-pack__col-head">
                                    <h5>{{shiftsTitle}}</h5>
                                    <label class="sh-avail-pack__select-all">
                                        <input type="checkbox" data-name="selectAllSlots"
                                            {{#if allSlotsSelected}}checked{{/if}}>
                                        <span>{{selectAllText}}</span>
                                    </label>
                                </header>
                                <div class="sh-avail-pack__list">
                                    {{#each slots}}
                                        <label class="sh-avail-pack__row">
                                            <input type="checkbox" data-slot-id="{{id}}"
                                                {{#if selected}}checked{{/if}}>
                                            <span class="sh-avail-pack__row-body">
                                                <span class="sh-avail-pack__row-title">
                                                    <strong>{{title}}</strong>
                                                </span>
                                                <span class="sh-avail-pack__row-meta text-muted">{{meta}}</span>
                                            </span>
                                        </label>
                                    {{/each}}
                                </div>
                            </section>
                        </div>
                        <p class="sh-avail-pack__summary text-muted">{{summaryText}}</p>
                    {{/if}}
                {{/if}}
            </div>
        `,

        data: function () {
            const selectedUsers = this.selectedUserMap || {};
            const selectedSlots = this.selectedSlotMap || {};
            const respondedLabel = this.translate(
                'volunteerRespondedSingular',
                'labels',
                'ActivityOffer'
            );

            const volunteers = (this.volunteers || []).map(row => {
                const labels = row.competenceLabels || [];

                return {
                    id: row.id,
                    name: row.name || row.id,
                    responded: !!row.responded,
                    respondedLabel: respondedLabel,
                    competenceText: labels.length ? labels.join(', ') : '',
                    selected: !!selectedUsers[row.id],
                };
            });

            const slots = (this.slots || []).map(row => ({
                id: row.id,
                title: row.title,
                meta: row.meta,
                selected: !!selectedSlots[row.id],
            }));

            const userCount = Object.keys(selectedUsers).length;
            const slotCount = Object.keys(selectedSlots).length;

            return {
                loading: !!this.loading,
                empty: !this.loading && (volunteers.length === 0 || slots.length === 0),
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
                peopleTitle: this.translate(
                    'requestAvailabilitySelectedPeople',
                    'labels',
                    'ActivityOffer'
                ),
                shiftsTitle: this.translate(
                    'requestAvailabilitySelectedShifts',
                    'labels',
                    'ActivityOffer'
                ),
                selectAllText: this.translate('Select All'),
                allUsersSelected: volunteers.length > 0 && userCount === volunteers.length,
                allSlotsSelected: slots.length > 0 && slotCount === slots.length,
                volunteers: volunteers,
                slots: slots,
                summaryText: this.translate(
                    'requestAvailabilitySelectedSummary',
                    'messages',
                    'ActivityOffer'
                )
                    .replace('{userCount}', String(userCount))
                    .replace('{slotCount}', String(slotCount)),
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
            this.slots = [];
            this.selectedUserMap = {};
            this.selectedSlotMap = {};

            this.wait(true);

            Promise.all([
                Espo.Ajax.getRequest('ActivityOffer/action/volunteerStats', {id: this.options.id}),
                Espo.Ajax.getRequest('ActivityOffer/action/coverage', {id: this.options.id}),
            ])
                .then(([stats, coverage]) => {
                    this.volunteers = (stats && stats.volunteers) || [];
                    this.slots = this.mapSlots((coverage && coverage.slots) || []);

                    // Default: all shifts selected (full pack); users start unchecked.
                    this.selectedSlotMap = {};
                    this.slots.forEach(slot => {
                        this.selectedSlotMap[slot.id] = true;
                    });

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

        mapSlots: function (rawList) {
            const skip = {Cancelled: true, Completed: true};

            return (rawList || [])
                .filter(row => row && row.id && !skip[row.status])
                .map(row => {
                    const title = row.categoryLabel || row.name || row.id;
                    const when = this.formatSlotWhen(row.dateStart, row.dateEnd);
                    const place = row.placeLabel || '';
                    const metaParts = [];

                    if (when) {
                        metaParts.push(when);
                    }

                    if (place) {
                        metaParts.push(place);
                    }

                    if (row.requiredCount) {
                        metaParts.push(
                            String(row.requiredCount) + ' · ' +
                            this.translate(
                                row.requiredCount === 1 ? 'slotPersonSingular' : 'slotPersonPlural',
                                'labels',
                                'ActivityOffer'
                            )
                        );
                    }

                    return {
                        id: row.id,
                        title: title,
                        meta: metaParts.join(' · '),
                    };
                });
        },

        formatSlotWhen: function (dateStart, dateEnd) {
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

        afterRender: function () {
            this.$el.find('[data-name="selectAllUsers"]')
                .off('change.selectedPack')
                .on('change.selectedPack', e => {
                    const checked = !!e.currentTarget.checked;

                    this.selectedUserMap = {};

                    if (checked) {
                        (this.volunteers || []).forEach(row => {
                            this.selectedUserMap[row.id] = true;
                        });
                    }

                    this.reRender();
                    this.syncSendButton();
                });

            this.$el.find('[data-name="selectAllSlots"]')
                .off('change.selectedPack')
                .on('change.selectedPack', e => {
                    const checked = !!e.currentTarget.checked;

                    this.selectedSlotMap = {};

                    if (checked) {
                        (this.slots || []).forEach(row => {
                            this.selectedSlotMap[row.id] = true;
                        });
                    }

                    this.reRender();
                    this.syncSendButton();
                });

            this.$el.find('[data-user-id]')
                .off('change.selectedPack')
                .on('change.selectedPack', e => {
                    const userId = e.currentTarget.getAttribute('data-user-id');

                    if (!userId) {
                        return;
                    }

                    if (e.currentTarget.checked) {
                        this.selectedUserMap[userId] = true;
                    }
                    else {
                        delete this.selectedUserMap[userId];
                    }

                    this.syncSelectAllChecks();
                    this.syncSendButton();
                });

            this.$el.find('[data-slot-id]')
                .off('change.selectedPack')
                .on('change.selectedPack', e => {
                    const slotId = e.currentTarget.getAttribute('data-slot-id');

                    if (!slotId) {
                        return;
                    }

                    if (e.currentTarget.checked) {
                        this.selectedSlotMap[slotId] = true;
                    }
                    else {
                        delete this.selectedSlotMap[slotId];
                    }

                    this.syncSelectAllChecks();
                    this.syncSendButton();
                });

            this.syncSendButton();
        },

        syncSelectAllChecks: function () {
            this.$el.find('[data-name="selectAllUsers"]').prop(
                'checked',
                this.volunteers.length > 0 &&
                    Object.keys(this.selectedUserMap).length === this.volunteers.length
            );
            this.$el.find('[data-name="selectAllSlots"]').prop(
                'checked',
                this.slots.length > 0 &&
                    Object.keys(this.selectedSlotMap).length === this.slots.length
            );

            const summary = this.translate(
                'requestAvailabilitySelectedSummary',
                'messages',
                'ActivityOffer'
            )
                .replace('{userCount}', String(Object.keys(this.selectedUserMap).length))
                .replace('{slotCount}', String(Object.keys(this.selectedSlotMap).length));

            this.$el.find('.sh-avail-pack__summary').text(summary);
        },

        syncSendButton: function () {
            const ready =
                Object.keys(this.selectedUserMap || {}).length > 0 &&
                Object.keys(this.selectedSlotMap || {}).length > 0;

            if (ready) {
                this.enableButton('send');
            }
            else {
                this.disableButton('send');
            }
        },

        actionSend: function () {
            const userIds = Object.keys(this.selectedUserMap || {});
            const slotIds = Object.keys(this.selectedSlotMap || {});

            if (!userIds.length || !slotIds.length) {
                return;
            }

            this.disableButton('send');
            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('ActivityOffer/action/requestAvailabilityForUsers', {
                    id: this.options.id,
                    userIds: userIds,
                    slotIds: slotIds,
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
