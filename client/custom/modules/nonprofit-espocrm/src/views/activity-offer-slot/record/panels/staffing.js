define('nonprofit-espocrm:views/activity-offer-slot/record/panels/staffing', [
    'views/record/panels/side',
], function (Dep) {

    /**
     * Assigned + candidates as responsive micro-cards (avatar, name, status).
     */
    return Dep.extend({

        templateContent: `
            <div class="slot-staffing-panel">
                <div class="slot-staffing-section">
                    <div class="slot-staffing-heading">{{assignedTitle}}</div>
                    {{#if assigned.length}}
                        <div class="slot-staffing-grid">
                            {{#each assigned}}
                                <div class="slot-staffing-card">
                                    <a href="#User/view/{{id}}" class="slot-staffing-avatar-link" title="{{name}}">
                                        {{{avatarHtml}}}
                                    </a>
                                    <a href="#User/view/{{id}}" class="slot-staffing-name text-default">{{name}}</a>
                                    <span class="label label-state label-status-semantic slot-staffing-status" data-status="{{status}}">{{statusLabel}}</span>
                                </div>
                            {{/each}}
                        </div>
                    {{else}}
                        <div class="text-muted small">{{noneAssigned}}</div>
                    {{/if}}
                </div>
                <div class="slot-staffing-section">
                    <div class="slot-staffing-heading">{{candidatesTitle}}</div>
                    {{#if candidates.length}}
                        <div class="slot-staffing-grid">
                            {{#each candidates}}
                                <div class="slot-staffing-card">
                                    <a href="#User/view/{{id}}" class="slot-staffing-avatar-link" title="{{name}}">
                                        {{{avatarHtml}}}
                                    </a>
                                    <a href="#User/view/{{id}}" class="slot-staffing-name text-default">{{name}}</a>
                                    <span class="label label-state label-status-semantic slot-staffing-status" data-status="{{status}}">{{statusLabel}}</span>
                                    {{#if ../canResend}}
                                        <button type="button"
                                                class="btn btn-default btn-xs action slot-staffing-resend"
                                                data-action="resendInvite"
                                                data-user-id="{{id}}"
                                                data-user-name="{{name}}"
                                                title="{{../resendTitle}}">
                                            <span class="fas fa-paper-plane"></span>
                                            {{../resendLabel}}
                                        </button>
                                    {{/if}}
                                </div>
                            {{/each}}
                        </div>
                    {{else}}
                        <div class="text-muted small">{{noneCandidates}}</div>
                    {{/if}}
                </div>
            </div>
        `,

        data: function () {
            const mapPerson = (row) => {
                const status = row.status || '';
                const avatarHtml = this.getHelper().getAvatarHtml(row.id, 'small', 40, 'slot-staffing-avatar')
                    || '<span class="slot-staffing-avatar slot-staffing-avatar-fallback fas fa-user"></span>';

                return {
                    id: row.id,
                    name: row.name,
                    status: status,
                    statusLabel: this.getLanguage()
                        .translateOption(status, 'status', 'ActivityInvite'),
                    statusStyle: status === 'Confirmed' ? 'success'
                        : (status === 'Assigned' ? 'warning' : 'default'),
                    avatarHtml: avatarHtml,
                };
            };

            return {
                assigned: (this.assigned || []).map(mapPerson),
                candidates: (this.candidates || []).map(mapPerson),
                canResend: !!this.canResend,
                assignedTitle: this.translate('slotAssigned', 'labels', 'ActivityOfferSlot'),
                candidatesTitle: this.translate('slotCandidates', 'labels', 'ActivityOfferSlot'),
                noneAssigned: this.translate('slotNoneAssigned', 'messages', 'ActivityOfferSlot'),
                noneCandidates: this.translate('slotNoneCandidates', 'messages', 'ActivityOfferSlot'),
                resendLabel: this.translate('resendInvite', 'labels', 'ActivityOfferSlot'),
                resendTitle: this.translate('resendInviteTooltip', 'messages', 'ActivityOfferSlot'),
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.assigned = [];
            this.candidates = [];
            this.canResend = false;
            this.buttonList = [];
            this._fetchTimer = null;
            this._skipNextFetch = false;
            this._staffingFetchErrorShown = false;

            this.listenTo(this.model, 'sync', () => this.scheduleFetch());
            this.listenTo(this.model, 'update-related:invites', () => this.scheduleFetch());
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            // reRender() after a successful fetch must not re-hit the API in a loop.
            if (this._skipNextFetch) {
                this._skipNextFetch = false;

                return;
            }

            this.scheduleFetch();
        },

        scheduleFetch: function () {
            if (this._fetchTimer) {
                clearTimeout(this._fetchTimer);
            }

            this._fetchTimer = setTimeout(() => this.fetchStaffing(), 80);
        },

        fetchStaffing: function () {
            if (!this.model || !this.model.id) {
                return;
            }

            Espo.Ajax.getRequest('ActivityOfferSlot/action/staffing', {id: this.model.id})
                .then(response => {
                    this.assigned = response.assigned || [];
                    this.candidates = response.candidates || [];
                    this.canResend = !!response.canResend;
                    this._staffingFetchErrorShown = false;
                    this._skipNextFetch = true;
                    this.reRender();
                })
                .catch(xhr => {
                    this.assigned = [];
                    this.candidates = [];
                    this.canResend = false;

                    if (!this._staffingFetchErrorShown) {
                        this._staffingFetchErrorShown = true;

                        const msg = (xhr && xhr.responseJSON && xhr.responseJSON.message)
                            || this.translate('Error');

                        Espo.Ui.error(msg);
                    }

                    this._skipNextFetch = true;
                    this.reRender();
                });
        },

        actionResendInvite: function (data) {
            const userId = data.userId;
            const userName = data.userName || userId;

            if (!userId || !this.model.id) {
                return;
            }

            Espo.Ui.confirm(
                this.translate('confirmResendInvite', 'messages', 'ActivityOfferSlot')
                    .replace('{name}', userName),
                {
                    confirmText: this.translate('resendInvite', 'labels', 'ActivityOfferSlot'),
                    cancelText: this.translate('Cancel'),
                }
            ).then(() => {
                Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                Espo.Ajax.postRequest('ActivityOfferSlot/action/resendInvite', {
                    id: this.model.id,
                    userId: userId,
                }).then(result => {
                    const sent = result && result.emailSent ? result.emailSent : 0;
                    const msg = this.translate('resendInviteDone', 'messages', 'ActivityOfferSlot')
                        .replace('{name}', userName)
                        .replace('{emailSent}', String(sent));

                    Espo.Ui.success(msg);
                }).catch(() => {
                    Espo.Ui.error(this.translate('Error'));
                });
            });
        },
    });
});
