define('safehouse-crm:handlers/reporting/email-export', [], function () {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        send() {
            const searchPayload = this.buildSearchPayload();

            this.view.createView('dialog', 'safehouse-crm:views/modals/meal-count-email-export', {
                searchPayload: searchPayload,
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
