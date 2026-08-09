define('nonprofit-espocrm:views/activity-offer/modals/availability', ['views/modal'], function (Dep) {

    /**
     * Volunteer availability grid: checkboxes per shift, place, conditions, comment.
     * After hard re-collect, changed slots are listed first (Available was cleared).
     */
    return Dep.extend({

        className: 'dialog dialog-record',

        backdrop: true,

        templateContent: `
            <div class="availability-grid">
                {{#unless canRespond}}
                    <div class="alert alert-warning">{{notOpenText}}</div>
                {{/unless}}
                {{#if canRespond}}
                    <div class="alert alert-info">{{notFinalHint}}</div>
                {{/if}}
                {{#if description}}
                    <p class="text-muted">{{description}}</p>
                {{/if}}
                {{#if placeLabel}}
                    <p><strong>{{placeText}}:</strong> {{placeLabel}}</p>
                {{/if}}
                {{#each groups}}
                    <div class="availability-group" style="margin-bottom: 1.2em;">
                        <h5 style="margin: 0 0 0.4em 0;">{{label}}</h5>
                        {{#each slots}}
                            <label
                                class="availability-slot"
                                style="display: block; padding: 0.55em 0.35em; border-bottom: 1px solid var(--border-color, #4443); cursor: {{#if enabled}}pointer{{else}}default{{/if}};"
                            >
                                <div style="display: flex; align-items: flex-start; gap: 0.6em; flex-wrap: wrap;">
                                    <input
                                        type="checkbox"
                                        data-slot-id="{{id}}"
                                        {{#if checked}}checked{{/if}}
                                        {{#unless enabled}}disabled{{/unless}}
                                        style="margin: 0.25em 0 0 0;"
                                    >
                                    <div style="flex: 1; min-width: 12em;">
                                        <div>
                                            <strong>{{dayLabel}}</strong>
                                            <span class="text-muted"> · {{timeLabel}}</span>
                                            {{#if statusLabel}}
                                                <span
                                                    class="label label-state label-status-semantic label-{{statusStyle}}"
                                                    data-status="{{myStatus}}"
                                                >{{statusLabel}}</span>
                                            {{/if}}
                                        </div>
                                        {{#if placeLabel}}
                                            <div class="small text-muted">{{placeText}}: {{placeLabel}}</div>
                                        {{/if}}
                                        {{#if conditionsLabel}}
                                            <div class="small">{{conditionsText}}: {{conditionsLabel}}</div>
                                        {{/if}}
                                        <div class="small text-muted">{{requiredText}}: {{requiredCount}}</div>
                                    </div>
                                </div>
                            </label>
                        {{/each}}
                    </div>
                {{/each}}
                <div class="form-group" style="margin-top: 1em;">
                    <label class="control-label">{{commentText}}</label>
                    <textarea
                        class="form-control"
                        data-name="comment"
                        rows="3"
                        maxlength="2000"
                        {{#unless canRespond}}disabled{{/unless}}
                    >{{comment}}</textarea>
                </div>
                {{#if hasDisallowed}}
                    <p class="text-muted small">{{disallowedText}}</p>
                {{/if}}
            </div>
        `,

        data: function () {
            return {
                canRespond: this.gridData.canRespond,
                description: this.gridData.description,
                placeLabel: this.gridData.placeLabel || '',
                comment: this.comment || '',
                groups: this.groups,
                hasDisallowed: this.hasDisallowed,
                notOpenText: this.translate('availabilityNotOpen', 'messages', 'ActivityOffer'),
                notFinalHint: this.translate('availabilityNotFinalHint', 'messages', 'ActivityOffer'),
                disallowedText: this.translate('availabilityDisallowedHint', 'messages', 'ActivityOffer'),
                placeText: this.translate('place', 'fields', 'ActivityOfferSlot'),
                conditionsText: this.translate('conditions', 'fields', 'ActivityOfferSlot'),
                requiredText: this.translate('requiredCount', 'fields', 'ActivityOfferSlot'),
                commentText: this.translate('comment', 'fields', 'ActivityInvite'),
            };
        },

        setup: function () {
            this.headerText = this.translate('Fill availability', 'labels', 'ActivityOffer');

            this.buttonList = [
                {
                    name: 'save',
                    label: 'Save',
                    style: 'primary',
                    disabled: false,
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];

            this.gridData = {slots: [], canRespond: false};
            this.groups = [];
            this.hasDisallowed = false;
            this.comment = '';

            this.wait(
                Espo.Ajax
                    .getRequest('ActivityOffer/action/availabilityGrid', {id: this.options.id})
                    .then(data => {
                        this.gridData = data;
                        this.comment = data.comment || '';
                        this.prepareGroups();

                        this.headerText = (data.name || '') + ' · ' +
                            this.translate('Fill availability', 'labels', 'ActivityOffer');

                        if (!data.canRespond) {
                            this.disableButton('save');
                        }
                    })
            );
        },

        prepareGroups: function () {
            const statusStyleMap = {
                Available: 'primary',
                Assigned: 'warning',
                Confirmed: 'success',
                Declined: 'danger',
                Cancelled: 'default',
            };

            const changedSectionLabel = this.translate(
                'availabilitySectionChanged',
                'labels',
                'ActivityOffer'
            );
            const unchangedSectionLabel = this.translate(
                'availabilitySectionUnchanged',
                'labels',
                'ActivityOffer'
            );

            const mapSlot = slot => {
                if (!slot.allowed) {
                    this.hasDisallowed = true;
                }

                const checked = ['Available', 'Assigned', 'Confirmed'].includes(slot.myStatus);
                const volunteerLabel = this.volunteerFacingStatusLabel(slot.myStatus);

                return {
                    id: slot.id,
                    dayLabel: this.formatDay(slot.dateStart),
                    timeLabel: this.formatTimeRange(slot.dateStart, slot.dateEnd),
                    placeLabel: slot.placeLabel || '',
                    conditionsLabel: (slot.conditions || []).join('; '),
                    requiredCount: slot.requiredCount,
                    checked: checked,
                    enabled: this.gridData.canRespond && slot.allowed,
                    myStatus: slot.myStatus || '',
                    statusLabel: volunteerLabel,
                    statusStyle: statusStyleMap[slot.myStatus] || 'default',
                    placeText: this.translate('place', 'fields', 'ActivityOfferSlot'),
                    conditionsText: this.translate('conditions', 'fields', 'ActivityOfferSlot'),
                    requiredText: this.translate('requiredCount', 'fields', 'ActivityOfferSlot'),
                };
            };

            const slots = this.gridData.slots || [];
            const hasChangedTracking = slots.some(s => !!s.changed) ||
                ((this.gridData.changedSlotIds || []).length > 0);

            if (hasChangedTracking) {
                const changed = slots.filter(s => !!s.changed).map(mapSlot);
                const unchanged = slots.filter(s => !s.changed).map(mapSlot);

                this.groups = [];

                if (changed.length) {
                    this.groups.push({label: changedSectionLabel, slots: changed});
                }

                if (unchanged.length) {
                    this.groups.push({label: unchangedSectionLabel, slots: unchanged});
                }

                return;
            }

            const groupMap = {};

            slots.forEach(slot => {
                const key = slot.category || '';

                if (!groupMap[key]) {
                    groupMap[key] = {
                        label: slot.categoryLabel || key ||
                            this.translate('Shifts', 'labels', 'ActivityOffer'),
                        slots: [],
                    };
                }

                groupMap[key].slots.push(mapSlot(slot));
            });

            this.groups = Object.keys(groupMap).map(key => groupMap[key]);
        },

        volunteerFacingStatusLabel: function (status) {
            if (!status || status === 'Available') {
                return null;
            }

            if (status === 'Assigned') {
                return this.translate('pendingConfirmation', 'labels', 'ActivityOffer');
            }

            return this.getLanguage().translateOption(status, 'status', 'ActivityInvite');
        },

        formatDay: function (dateStart) {
            if (!dateStart) {
                return '';
            }

            const m = this.getDateTime().toMoment(dateStart);

            return m.format('dddd D MMM');
        },

        formatTimeRange: function (dateStart, dateEnd) {
            if (!dateStart) {
                return '';
            }

            const timeFormat = this.getDateTime().timeFormat;
            const start = this.getDateTime().toMoment(dateStart).format(timeFormat);

            if (!dateEnd) {
                return start;
            }

            const end = this.getDateTime().toMoment(dateEnd).format(timeFormat);

            return start + ' – ' + end;
        },

        actionSave: function () {
            const slotIds = [];

            this.$el.find('input[data-slot-id]').each((i, el) => {
                if (el.checked) {
                    slotIds.push(el.getAttribute('data-slot-id'));
                }
            });

            const comment = (this.$el.find('[data-name="comment"]').val() || '').trim();

            this.disableButton('save');
            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('ActivityOffer/action/saveAvailability', {
                    id: this.options.id,
                    slotIds: slotIds,
                    comment: comment,
                })
                .then(() => {
                    Espo.Ui.success(
                        this.translate('availabilitySaved', 'messages', 'ActivityOffer')
                    );

                    this.trigger('saved');
                    this.close();
                })
                .catch(() => {
                    this.enableButton('save');
                });
        },
    });
});
