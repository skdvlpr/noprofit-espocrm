define('volunteer-activity-dispatch:views/activity-offer/modals/availability', ['views/modal'], function (Dep) {

    /**
     * Volunteer availability grid: checkboxes per shift, grouped by category.
     */
    return Dep.extend({

        className: 'dialog dialog-record',

        backdrop: true,

        templateContent: `
            <div class="availability-grid">
                {{#unless canRespond}}
                    <div class="alert alert-warning">{{notOpenText}}</div>
                {{/unless}}
                {{#if description}}
                    <p class="text-muted">{{description}}</p>
                {{/if}}
                {{#each groups}}
                    <div class="availability-group" style="margin-bottom: 1.2em;">
                        <h5 style="margin: 0 0 0.4em 0;">{{label}}</h5>
                        {{#each slots}}
                            <label
                                class="availability-slot"
                                style="display: flex; align-items: center; gap: 0.6em; padding: 0.35em 0.2em; cursor: pointer; flex-wrap: wrap;"
                            >
                                <input
                                    type="checkbox"
                                    data-slot-id="{{id}}"
                                    {{#if checked}}checked{{/if}}
                                    {{#unless enabled}}disabled{{/unless}}
                                    style="margin: 0;"
                                >
                                <span style="min-width: 11em;">{{dayLabel}}</span>
                                <span class="text-muted">{{timeLabel}}</span>
                                {{#if statusLabel}}
                                    <span class="label label-{{statusStyle}}">{{statusLabel}}</span>
                                {{/if}}
                            </label>
                        {{/each}}
                    </div>
                {{/each}}
                {{#if hasDisallowed}}
                    <p class="text-muted small">{{disallowedText}}</p>
                {{/if}}
            </div>
        `,

        data: function () {
            return {
                canRespond: this.gridData.canRespond,
                description: this.gridData.description,
                groups: this.groups,
                hasDisallowed: this.hasDisallowed,
                notOpenText: this.translate('availabilityNotOpen', 'messages', 'ActivityOffer'),
                disallowedText: this.translate('availabilityDisallowedHint', 'messages', 'ActivityOffer'),
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

            this.wait(
                Espo.Ajax
                    .getRequest('ActivityOffer/action/availabilityGrid', {id: this.options.id})
                    .then(data => {
                        this.gridData = data;
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
            const groupMap = {};
            const statusStyleMap = {
                Available: 'primary',
                Assigned: 'warning',
                Confirmed: 'success',
                Declined: 'danger',
                Cancelled: 'default',
            };

            (this.gridData.slots || []).forEach(slot => {
                const key = slot.category || '';

                if (!groupMap[key]) {
                    groupMap[key] = {
                        label: slot.categoryLabel || key,
                        slots: [],
                    };
                }

                if (!slot.allowed) {
                    this.hasDisallowed = true;
                }

                const checked = ['Available', 'Assigned', 'Confirmed'].includes(slot.myStatus);

                groupMap[key].slots.push({
                    id: slot.id,
                    dayLabel: this.formatDay(slot.dateStart),
                    timeLabel: this.formatTimeRange(slot.dateStart, slot.dateEnd),
                    checked: checked,
                    enabled: this.gridData.canRespond && slot.allowed,
                    statusLabel: slot.myStatus && slot.myStatus !== 'Available' ?
                        this.getLanguage().translateOption(slot.myStatus, 'status', 'ActivityInvite') :
                        null,
                    statusStyle: statusStyleMap[slot.myStatus] || 'default',
                });
            });

            this.groups = Object.keys(groupMap).map(key => groupMap[key]);
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

            this.disableButton('save');
            Espo.Ui.notify('...');

            Espo.Ajax
                .postRequest('ActivityOffer/action/saveAvailability', {
                    id: this.options.id,
                    slotIds: slotIds,
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
