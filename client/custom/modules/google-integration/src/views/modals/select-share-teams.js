define('google-integration:views/modals/select-share-teams', [
    'exports',
    'views/modal',
    'model',
], function (_exports, ModalDep, ModelDep) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    const Modal = ModalDep && ModalDep.__esModule ? ModalDep.default : ModalDep;
    const Model = ModelDep && ModelDep.__esModule ? ModelDep.default : ModelDep;

    class SelectShareTeamsModal extends Modal {
        template = 'google-integration:modals/select-share-teams';
        cssName = 'select-share-teams-modal';
        className = 'dialog dialog-record gi-select-share-teams-dialog';
        backdrop = true;
        fitHeight = true;

        setup() {
            this.teams = Array.isArray(this.options.teams) ? this.options.teams : [];
            this.selectedIds = new Set(
                (this.options.selectedIds || []).map(id => String(id))
            );
            // Teams start expanded (members visible). User may collapse.
            this.collapsedIds = new Set();
            this.query = '';

            this.buttonList = [
                {
                    name: 'select',
                    style: 'danger',
                    label: 'Select',
                    onClick: () => this.actionConfirmSelect(),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: dialog => dialog.close(),
                },
            ];

            this.headerText =
                this.translate('googleCalendarShareTeamsSelectTitle', 'labels', 'Global') ||
                this.translate('Team', 'scopeNamesPlural');

            this.addHandler('input', '[data-name="teamSearch"]', e => {
                const target = e.currentTarget || e.target;

                this.query = String((target && target.value) || '').trim().toLowerCase();
                this.reRender();
            });

            this.addHandler('change', '[data-role="team-check"]', e => {
                const target = e.currentTarget || e.target;
                const id = String((target && target.getAttribute('data-id')) || '');

                if (!id) {
                    return;
                }

                if (target.checked) {
                    this.selectedIds.add(id);
                }
                else {
                    this.selectedIds.delete(id);
                }

                this.refreshSelectedCount();
                this.refreshCardSelectedState(id, target.checked);
            });

            // Espo routes data-action via addActionHandler (Modal listens on .action).
            this.addActionHandler('toggleMembers', (e, target) => {
                if (e && typeof e.preventDefault === 'function') {
                    e.preventDefault();
                }

                if (e && typeof e.stopPropagation === 'function') {
                    e.stopPropagation();
                }

                this.toggleTeamMembers(target);
            });
        }

        toggleTeamMembers(target) {
            const btn = target && target.closest
                ? target.closest('[data-action="toggleMembers"]')
                : target;

            if (!btn) {
                return;
            }

            const card = btn.closest('.gi-share-team-card');

            if (!card) {
                return;
            }

            const id = String(card.getAttribute('data-id') || '');
            const willCollapse = !card.classList.contains('is-collapsed');

            card.classList.toggle('is-collapsed', willCollapse);
            btn.setAttribute('aria-expanded', willCollapse ? 'false' : 'true');

            if (!id) {
                return;
            }

            if (willCollapse) {
                this.collapsedIds.add(id);
            }
            else {
                this.collapsedIds.delete(id);
            }
        }

        data() {
            return {
                teams: this.getVisibleTeams(),
                searchPlaceholder: this.translate('Search') || 'Search',
                googleLabel:
                    this.translate('googleCalendarShareGoogleBadge', 'labels', 'Global') ||
                    'Google',
                membersLabel:
                    this.translate('googleCalendarShareMembers', 'labels', 'Global') ||
                    'Members',
                emptyText:
                    this.translate('googleCalendarShareTeamsEmptySearch', 'labels', 'Global') ||
                    'No teams match the search.',
                selectedCount: this.selectedIds.size,
            };
        }

        getVisibleTeams() {
            const q = this.query;

            return this.teams
                .filter(team => {
                    if (!q) {
                        return true;
                    }

                    return String(team.name || '').toLowerCase().indexOf(q) !== -1;
                })
                .map(team => {
                    const id = String(team.id || '');
                    const members = Array.isArray(team.members) ? team.members : [];
                    const memberCount = Number(team.memberCount != null ? team.memberCount : members.length) || 0;
                    const googleConnectedCount = Number(
                        team.googleConnectedCount != null
                            ? team.googleConnectedCount
                            : members.filter(m => m && m.googleConnected).length
                    ) || 0;

                    return {
                        ...team,
                        id,
                        members,
                        memberCount,
                        googleConnectedCount,
                        selected: this.selectedIds.has(id),
                        collapsed: this.collapsedIds.has(id),
                        googleRatio: String(googleConnectedCount) + ' / ' + String(memberCount),
                        hasGoogle: googleConnectedCount > 0,
                    };
                });
        }

        afterRender() {
            super.afterRender();
            this.refreshSelectedCount();

            const input = this.element
                ? this.element.querySelector('[data-name="teamSearch"]')
                : null;

            if (input && this.query) {
                input.value = this.query;
            }

            (this.element ? this.element.querySelectorAll('.gi-share-team-card') : []).forEach(card => {
                const id = String(card.getAttribute('data-id') || '');
                const collapsed = this.collapsedIds.has(id);

                card.classList.toggle('is-collapsed', collapsed);

                const btn = card.querySelector('[data-action="toggleMembers"]');

                if (btn) {
                    btn.setAttribute('aria-expanded', collapsed ? 'false' : 'true');
                }
            });
        }

        refreshSelectedCount() {
            const el = this.element
                ? this.element.querySelector('[data-name="selectedCount"]')
                : null;

            if (el) {
                el.textContent = String(this.selectedIds.size);
            }
        }

        refreshCardSelectedState(id, selected) {
            const card = this.element
                ? this.element.querySelector('.gi-share-team-card[data-id="' + id + '"]')
                : null;

            if (card) {
                card.classList.toggle('is-selected', !!selected);
            }
        }

        actionConfirmSelect() {
            const models = [];

            this.teams.forEach(team => {
                if (!this.selectedIds.has(String(team.id))) {
                    return;
                }

                const model = new Model(null, {entityType: 'Team'});

                model.id = team.id;
                model.set({
                    id: team.id,
                    name: team.name,
                });

                models.push(model);
            });

            if (typeof this.options.onSelect === 'function') {
                this.options.onSelect(models);
            }

            this.close();
        }
    }

    _exports.default = SelectShareTeamsModal;
});
