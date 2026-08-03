define('nonprofit-espocrm:views/user/record/role-profile-mixin', [], function () {

    /**
     * Keep hasVolunteerRole / hasMemberRole in sync with selected role names
     * so dynamicLogic can show volunteer/member panels on create/edit.
     */
    return {

        setupRoleProfileFlags() {
            this.listenTo(this.model, 'change:rolesIds change:rolesNames', () => {
                this.syncRoleProfileFlags();
            });

            this.listenTo(this.model, 'change:weeklyHours', () => {
                this.syncMonthlyHoursFromWeekly();
            });

            this.syncRoleProfileFlags();
        },

        syncRoleProfileFlags() {
            const names = this.collectRoleNames();

            this.model.set({
                hasVolunteerRole: names.includes('Volunteer'),
                hasMemberRole: names.includes('Member'),
            }, {ui: true});
        },

        collectRoleNames() {
            const namesMap = this.model.get('rolesNames') || {};
            const fromMap = Object.values(namesMap).filter(v => typeof v === 'string' && v !== '');

            if (fromMap.length) {
                return fromMap;
            }

            // Fallback: some UIs keep names in rolesColumns / nested structures.
            const ids = this.model.get('rolesIds') || [];
            const out = [];

            ids.forEach(id => {
                if (namesMap[id]) {
                    out.push(namesMap[id]);
                }
            });

            return out;
        },

        syncMonthlyHoursFromWeekly() {
            const weekly = this.model.get('weeklyHours');

            if (weekly === null || weekly === '' || typeof weekly === 'undefined') {
                this.model.set('monthlyHours', null, {ui: true});

                return;
            }

            const monthly = Math.round(Number(weekly) * 4.33 * 10) / 10;
            this.model.set('monthlyHours', monthly, {ui: true});
        },
    };
});
