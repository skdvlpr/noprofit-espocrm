define('safehouse-crm:handlers/reporting/email-export', [], function () {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        send() {
            const searchPayload = this.buildSearchPayload();
            const entityType = this.view.collection?.entityType ||
                this.view.scope ||
                this.view.entityType;

            this.view.createView('dialog', 'safehouse-crm:views/modals/reporting-email-export', {
                searchPayload: searchPayload,
                entityType: entityType,
            }, modalView => {
                modalView.render();
            });
        }

        buildSearchPayload() {
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
