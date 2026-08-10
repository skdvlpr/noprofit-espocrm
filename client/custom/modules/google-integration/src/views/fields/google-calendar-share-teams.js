define('google-integration:views/fields/google-calendar-share-teams', [
    'exports',
    'views/fields/link-multiple',
], function (_exports, Dep) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    Dep = Dep && Dep.__esModule ? Dep : {default: Dep};

    /**
     * Team multi-select with a custom modal that shows Google-connected members.
     */
    class GoogleCalendarShareTeamsField extends Dep.default {
        createDisabled = true;

        setup() {
            super.setup();

            this.pickerData = null;
            this.pickerDataPromise = null;

            this.loadPickerData();
        }

        loadPickerData(force) {
            if (!force && this.pickerDataPromise) {
                return this.pickerDataPromise;
            }

            this.pickerDataPromise = Espo.Ajax
                .getRequest('GoogleIntegration/calendar/share-picker-data')
                .then(data => {
                    this.pickerData = data || {users: [], connectedUserIds: [], teams: []};

                    if (!Array.isArray(this.pickerData.teams)) {
                        this.pickerData.teams = [];
                    }

                    if (this.isRendered()) {
                        this.renderSelectedTeamSummary();
                        this.renderShareHint();
                    }

                    return this.pickerData;
                })
                .catch(err => {
                    // Allow retry on next open; do not cache a failed empty payload.
                    this.pickerDataPromise = null;
                    this.pickerData = {users: [], connectedUserIds: [], teams: []};

                    throw err;
                });

            return this.pickerDataPromise;
        }

        afterRender() {
            super.afterRender();
            this.renderShareHint();
            this.renderSelectedTeamSummary();
        }

        renderShareHint() {
            if (!this.$el || !this.isEditMode()) {
                return;
            }

            this.$el.find('.gi-share-picker-hint').remove();

            let text = this.translate('googleCalendarShareTeamsHint', 'labels', this.entityType);

            if (!text || text === 'googleCalendarShareTeamsHint') {
                text = this.translate('googleCalendarShareTeamsHint', 'labels', 'Global');
            }

            const $hint = $('<div>')
                .addClass('gi-share-picker-hint help-block text-muted small')
                .text(text);

            this.$el.append($hint);
        }

        getTeamById(teamId) {
            const teams = (this.pickerData && this.pickerData.teams) || [];

            return teams.find(row => String(row.id) === String(teamId)) || null;
        }

        renderSelectedTeamSummary() {
            if (!this.$el) {
                return;
            }

            this.$el.find('.gi-share-team-summary').remove();

            const ids = this.ids || [];

            if (!ids.length || !this.pickerData) {
                return;
            }

            const $wrap = $('<div>').addClass('gi-share-team-summary');

            ids.forEach(id => {
                const team = this.getTeamById(id);

                if (!team) {
                    return;
                }

                const $card = $('<div>').addClass('gi-share-team-summary__card');
                const $head = $('<div>').addClass('gi-share-team-summary__head');
                const $title = $('<strong>').text(team.name);
                const $badge = $('<span>')
                    .addClass('gi-share-badge')
                    .text(
                        String(team.googleConnectedCount) +
                        ' / ' +
                        String(team.memberCount) +
                        ' Google'
                    );

                if (team.googleConnectedCount === 0) {
                    $badge.addClass('gi-share-badge--muted');
                }

                $head.append($title).append($badge);
                $card.append($head);

                const $members = $('<div>').addClass('gi-share-team-summary__members');

                (team.members || []).forEach(member => {
                    const $chip = $('<span>')
                        .addClass('gi-share-member-chip')
                        .toggleClass('gi-share-member-chip--google', !!member.googleConnected)
                        .toggleClass('gi-share-member-chip--muted', !member.googleConnected)
                        .attr('title', member.userName || member.name)
                        .text(member.name);

                    if (member.googleConnected) {
                        $chip.prepend(
                            $('<span>').addClass('gi-share-member-chip__icon fas fa-check')
                        );
                    }

                    $members.append($chip);
                });

                $card.append($members);
                $wrap.append($card);
            });

            if ($wrap.children().length) {
                this.$el.append($wrap);
            }
        }

        async actionSelect() {
            Espo.Ui.notifyWait();

            try {
                await this.loadPickerData(true);
            }
            catch (e) {
                Espo.Ui.notify(false);
                Espo.Ui.error(this.translate('Error'));

                return;
            }

            const teams = (this.pickerData && this.pickerData.teams) || [];

            const view = await this.createView('modal', 'google-integration:views/modals/select-share-teams', {
                teams: teams,
                selectedIds: [...(this.ids || [])],
                onSelect: models => {
                    const keep = new Set(models.map(m => String(m.id)));
                    const current = [...(this.ids || [])];

                    current.forEach(id => {
                        if (!keep.has(String(id))) {
                            this.deleteLink(id);
                        }
                    });

                    const existing = new Set((this.ids || []).map(id => String(id)));
                    const toAdd = models.filter(m => !existing.has(String(m.id)));

                    if (toAdd.length) {
                        this.select(toAdd);
                    }

                    this.renderSelectedTeamSummary();
                },
            });

            await view.render();
            Espo.Ui.notify(false);
        }

        select(models) {
            super.select(models);
            this.renderSelectedTeamSummary();
        }

        deleteLink(id) {
            super.deleteLink(id);
            this.renderSelectedTeamSummary();
        }
    }

    _exports.default = GoogleCalendarShareTeamsField;
});
