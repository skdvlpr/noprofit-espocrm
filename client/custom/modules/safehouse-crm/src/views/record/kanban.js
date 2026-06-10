define('safehouse-crm:views/record/kanban', ['views/record/kanban'], function (Dep) {

    return Dep.extend({

        itemViewName: 'safehouse-crm:views/record/kanban-item',

        quickDetailDisabled: false,
        quickEditDisabled: false,

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
    });
});
