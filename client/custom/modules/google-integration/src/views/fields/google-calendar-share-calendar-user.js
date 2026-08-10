define('google-integration:views/fields/google-calendar-share-calendar-user', [
    'exports',
    'views/fields/enum',
], function (_exports, EnumDep) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    const Enum = EnumDep && EnumDep.__esModule ? EnumDep.default : EnumDep;

    /**
     * Owner of the Google account used for share "pick / create calendar".
     * Options = Google-connected users among selected share users/teams.
     * Consent is still required later for event fan-out (soft-skip).
     */
    class GoogleCalendarShareCalendarUserField extends Enum {
        setup() {
            super.setup();

            this.pickerData = null;
            this.ownerOptionList = [];
            this.ownersLoaded = false;

            this.loadOwners();

            this.listenTo(this.model, 'change:googleCalendarShareUsersIds', () => {
                this.refreshOwnerOptionsFromSelection();
            });

            this.listenTo(this.model, 'change:googleCalendarShareTeamsIds', () => {
                this.refreshOwnerOptionsFromSelection();
            });
        }

        setupOptions() {
            const current = String(this.model.get(this.name) || '');

            if (!this.ownersLoaded) {
                this.params.options = current ? [current] : [''];
                this.translatedOptions = {'': '—'};

                if (current) {
                    this.translatedOptions[current] = current;
                }

                super.setupOptions();

                return;
            }

            this.applyOwnerOptions();
            super.setupOptions();
        }

        applyOwnerOptions() {
            const options = [''];
            const labels = {'': '—'};

            this.ownerOptionList.forEach(row => {
                options.push(row.id);
                labels[row.id] = row.label || row.name || row.userName || row.id;
            });

            const current = String(this.model.get(this.name) || '');

            if (current && !options.includes(current)) {
                options.push(current);
                labels[current] = labels[current] || current;
            }

            this.params.options = options;
            this.translatedOptions = labels;
        }

        loadOwners() {
            Espo.Ajax.getRequest('GoogleIntegration/calendar/share-picker-data')
                .then(data => {
                    this.pickerData = data || {users: [], teams: []};
                    this.ownersLoaded = true;
                    this.refreshOwnerOptionsFromSelection();
                })
                .catch(() => {
                    this.pickerData = {users: [], teams: []};
                    this.ownerOptionList = [];
                    this.ownersLoaded = true;

                    if (this.isRendered()) {
                        this.reRender();
                    }
                });
        }

        formatOwnerLabel(row) {
            const name = String(row.name || row.userName || row.id);
            const consentSuffix = row.hasConsent
                ? ''
                : (' (' + (
                    this.translate('googleCalendarShareOwnerNoConsent', 'labels', 'Global') ||
                    'no manager consent'
                ) + ')');

            return name + consentSuffix;
        }

        refreshOwnerOptionsFromSelection() {
            if (!this.ownersLoaded || !this.pickerData) {
                return;
            }

            const users = Array.isArray(this.pickerData.users) ? this.pickerData.users : [];
            const teams = Array.isArray(this.pickerData.teams) ? this.pickerData.teams : [];
            const byId = {};

            // All users from picker are Google-connected.
            users.forEach(row => {
                if (!row || !row.id) {
                    return;
                }

                const id = String(row.id);

                byId[id] = {
                    id: id,
                    name: String(row.name || row.userName || id),
                    userName: String(row.userName || ''),
                    hasConsent: !!row.hasConsent,
                    label: null,
                };
                byId[id].label = this.formatOwnerLabel(byId[id]);
            });

            teams.forEach(team => {
                (team.members || []).forEach(member => {
                    if (!member || !member.id || !member.googleConnected) {
                        return;
                    }

                    const id = String(member.id);

                    if (!byId[id]) {
                        byId[id] = {
                            id: id,
                            name: String(member.name || member.userName || id),
                            userName: String(member.userName || ''),
                            hasConsent: !!member.hasConsent,
                            label: null,
                        };
                        byId[id].label = this.formatOwnerLabel(byId[id]);
                    }
                });
            });

            const shareUserIds = (this.model.get('googleCalendarShareUsersIds') || [])
                .map(id => String(id));
            const shareTeamIds = (this.model.get('googleCalendarShareTeamsIds') || [])
                .map(id => String(id));

            const scoped = {};

            shareUserIds.forEach(id => {
                if (byId[id]) {
                    scoped[id] = byId[id];
                }
            });

            teams.forEach(team => {
                if (!shareTeamIds.includes(String(team.id))) {
                    return;
                }

                (team.members || []).forEach(member => {
                    if (!member || !member.id || !member.googleConnected) {
                        return;
                    }

                    const id = String(member.id);

                    if (byId[id]) {
                        scoped[id] = byId[id];
                    }
                });
            });

            const hasShareSelection = shareUserIds.length > 0 || shareTeamIds.length > 0;
            const useScoped = hasShareSelection;
            const source = useScoped ? scoped : byId;

            this.ownerOptionList = Object.keys(source)
                .map(id => source[id])
                .sort((a, b) => {
                    if (!!a.hasConsent !== !!b.hasConsent) {
                        return a.hasConsent ? -1 : 1;
                    }

                    return String(a.name).localeCompare(String(b.name));
                });

            this.preferShareUserAsOwner();
            this.applyOwnerOptions();

            if (this.isRendered()) {
                this.reRender();
            }
        }

        preferShareUserAsOwner() {
            if (!this.ownersLoaded) {
                return;
            }

            const current = String(this.model.get(this.name) || '');
            const allowed = new Set(this.ownerOptionList.map(row => row.id));

            if (current && allowed.has(current)) {
                return;
            }

            // Prefer a consented owner when available.
            const preferred = this.ownerOptionList.find(row => row.hasConsent)
                || this.ownerOptionList[0];

            if (preferred) {
                this.model.set(this.name, preferred.id);
            }
            else if (current) {
                this.model.set(this.name, '');
            }
        }

        afterRender() {
            super.afterRender();

            if (this.mode !== 'edit' || !this.$el) {
                return;
            }

            this.$el.find('.gi-share-calendar-owner-hint').remove();

            let text = this.translate('googleCalendarSharePickOwnerHint', 'labels', this.entityType);

            if (!text || text === 'googleCalendarSharePickOwnerHint') {
                text = this.translate('googleCalendarSharePickOwnerHint', 'labels', 'Global');
            }

            if (!this.ownerOptionList.length && this.ownersLoaded) {
                let empty = this.translate('googleCalendarSharePickOwnerEmpty', 'labels', this.entityType);

                if (!empty || empty === 'googleCalendarSharePickOwnerEmpty') {
                    empty = this.translate('googleCalendarSharePickOwnerEmpty', 'labels', 'Global');
                }

                text = empty || text;
            }

            this.$el.append(
                $('<div>')
                    .addClass('gi-share-calendar-owner-hint help-block text-muted small')
                    .text(text)
            );
        }
    }

    _exports.default = GoogleCalendarShareCalendarUserField;
});
