define('safehouse-crm:handlers/reporting/email-export', [], function () {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        send() {
            const searchPayload = this.buildSearchPayload();
            const fieldList = this.buildFieldList();
            const entityType = this.view.collection?.entityType ||
                this.view.scope ||
                this.view.entityType;

            this.view.createView('dialog', 'safehouse-crm:views/modals/reporting-email-export', {
                searchPayload: searchPayload,
                entityType: entityType,
                fieldList: fieldList,
            }, modalView => {
                modalView.render();
            });
        }

        buildFieldList() {
            const layout = this.view.listLayout || [];
            const fieldList = [];

            layout.forEach(item => {
                if (item && item.name) {
                    fieldList.push(item.name);
                }
            });

            return fieldList.length ? fieldList : null;
        }

        buildSearchPayload() {
            const view = this.view;

            if (view.checkedList && view.checkedList.length && !view.allResultIsChecked) {
                return { ids: view.checkedList.slice() };
            }

            const searchManager = this.view.searchManager;

            if (searchManager) {
                const data = searchManager.get();

                return {
                    where: searchManager.getWhere(),
                    textFilter: data.textFilter || '',
                    primaryFilter: data.primary || null,
                    boolFilterList: Object.keys(data.bool || {}).filter(name => data.bool[name]),
                };
            }

            const collection = this.view.collection;

            return {
                where: collection.where || [],
                orderBy: collection.orderBy,
                order: collection.order,
            };
        }
    }

    return Handler;
});
