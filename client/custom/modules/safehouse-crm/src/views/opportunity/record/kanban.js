define('safehouse-crm:views/opportunity/record/kanban', ['crm:views/opportunity/record/kanban'], function (Dep) {

    const EXTRA_SELECT_ATTRIBUTES = [
        'assignedUserId',
        'assignedUserName',
        'teamsIds',
        'teamsNames',
        'saveToGoogleCalendar',
        'googleCalendarDateSourceList',
    ];

    return Dep.extend({
        itemViewName: 'safehouse-crm:views/record/kanban-item',

        setup() {
            Dep.prototype.setup.apply(this, arguments);

            this.on('after:save', model => {
                const collectionModel = this.collection.get(model.id);

                if (collectionModel && collectionModel !== model) {
                    collectionModel.set(model.getClonedAttributes(), {
                        sync: true,
                    });
                }

                const view = this.getView(model.id);

                if (!view) {
                    return;
                }

                if (typeof view.refreshAfterExternalSave === 'function') {
                    view.refreshAfterExternalSave();

                    return;
                }

                view.reRender();
            });
        },

        async getSelectAttributeList(callback) {
            const attributeList = await Dep.prototype.getSelectAttributeList.call(this, callback);

            if (!attributeList) {
                return null;
            }

            EXTRA_SELECT_ATTRIBUTES.forEach(name => {
                if (!attributeList.includes(name)) {
                    attributeList.push(name);
                }
            });

            return attributeList;
        },
    });
});
