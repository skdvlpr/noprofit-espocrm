define('nonprofit-espocrm:views/activity-offer/record/panels/tasks', [
    'views/record/panels/bottom',
], function (Dep) {

    /**
     * Personal tasks for the plan week (by date overlap), not only FK-linked rows.
     * onlyMy is forced for Volunteer/Employee; Admin / Manager / Member keep free filters.
     */
    return Dep.extend({

        templateContent: `
            <div class="list-container activity-offer-panel-scroll">{{{list}}}</div>
        `,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.scope = 'Task';
            this.forceOnlyMy = this.shouldForceOnlyMy();

            this.buttonList = [
                {
                    action: 'createTask',
                    title: this.translate('Create Task', 'labels', 'Task'),
                    html: '<span class="fas fa-plus"></span>',
                    acl: 'create',
                    aclScope: 'Task',
                },
                {
                    action: 'refresh',
                    title: this.translate('Refresh'),
                    html: '<span class="fas fa-sync"></span>',
                },
            ];

            this.wait(true);

            this.getCollectionFactory().create('Task', collection => {
                this.collection = collection;
                collection.maxSize = this.getConfig().get('recordsPerPageSmall') || 10;
                collection.url = 'Task';
                collection.orderBy = 'dateStart';
                collection.order = 'asc';

                this.applyWeekScope();
                this.applyOnlyMyDefault();

                this.listenTo(this.model, 'change:weekStart sync', () => {
                    this.applyWeekScope();
                    this.collection.fetch();
                });

                this.createView('list', 'views/record/list', {
                    collection: collection,
                    model: this.model,
                    selectable: false,
                    checkboxes: false,
                    massActionsDisabled: true,
                    recordActionsDisabled: false,
                    rowActionsView: 'views/record/row-actions/default',
                    buttonsDisabled: true,
                    listLayout: this.getListLayout(),
                    skipBuildRows: true,
                }, () => {
                    collection.fetch()
                        .then(() => this.wait(false))
                        .catch(() => this.wait(false));
                });
            });
        },

        /**
         * Volunteer / Employee → onlyMy.
         * Admin, Manager, Member → no forced onlyMy (they can filter themselves).
         */
        shouldForceOnlyMy: function () {
            if (this.getUser().isAdmin()) {
                return false;
            }

            const roleNames = Object.values(this.getUser().get('rolesNames') || {});

            if (roleNames.includes('Manager') || roleNames.includes('Member') || roleNames.includes('Admin')) {
                return false;
            }

            if (roleNames.includes('Volunteer') || roleNames.includes('Employee')) {
                return true;
            }

            // Regular users without organizer roles: personal view.
            return !this.getAcl().checkModel(this.model, 'edit');
        },

        applyOnlyMyDefault: function () {
            if (!this.collection) {
                return;
            }

            const list = Array.isArray(this.collection.data.boolFilterList)
                ? this.collection.data.boolFilterList.slice()
                : [];

            if (this.forceOnlyMy) {
                if (!list.includes('onlyMy')) {
                    list.push('onlyMy');
                }
            } else {
                const idx = list.indexOf('onlyMy');

                if (idx !== -1) {
                    list.splice(idx, 1);
                }
            }

            this.collection.data.boolFilterList = list;
        },

        applyWeekScope: function () {
            if (!this.collection) {
                return;
            }

            const weekStart = this.model.get('weekStart');

            if (!weekStart) {
                this.collection.where = [{
                    type: 'equals',
                    attribute: 'id',
                    value: '__none__',
                }];

                return;
            }

            const start = weekStart + ' 00:00:00';
            const endExclusive = this.weekEndExclusive(weekStart);

            // Tasks whose start falls in the plan week (covers planner + external tasks).
            this.collection.where = [
                {
                    type: 'and',
                    value: [
                        {
                            type: 'greaterThanOrEquals',
                            attribute: 'dateStart',
                            value: start,
                        },
                        {
                            type: 'lessThan',
                            attribute: 'dateStart',
                            value: endExclusive,
                        },
                    ],
                },
            ];
        },

        weekEndExclusive: function (weekStart) {
            const m = this.getDateTime().toMoment(weekStart + ' 00:00:00');

            if (!m || !m.isValid()) {
                return weekStart + ' 23:59:59';
            }

            return m.clone().add(7, 'days').format('YYYY-MM-DD HH:mm:ss');
        },

        getListLayout: function () {
            return [
                {name: 'name', link: true},
                {name: 'status'},
                {name: 'dateStart'},
                {name: 'assignedUser'},
            ];
        },

        actionRefresh: function () {
            this.applyWeekScope();
            this.applyOnlyMyDefault();
            this.collection.fetch();
        },

        actionCreateTask: function () {
            if (!this.getAcl().check('Task', 'create')) {
                this.notify('Access denied', 'error');

                return;
            }

            const weekStart = this.model.get('weekStart');
            const attributes = {
                assignedUserId: this.getUser().id,
                assignedUserName: this.getUser().get('name'),
            };

            if (weekStart) {
                attributes.dateStart = weekStart + ' 09:00:00';
                attributes.dateEnd = weekStart + ' 10:00:00';
            }

            Espo.Ui.notify('...');

            this.createView('quickCreate', 'views/modals/edit', {
                scope: 'Task',
                attributes: attributes,
                fullFormDisabled: false,
            }, view => {
                view.render();

                this.listenToOnce(view, 'after:save', () => {
                    this.collection.fetch();
                    this.model.trigger('update-related:tasks');
                });
            });
        },
    });
});
