define('safehouse-crm:handlers/quick-view-kanban', [
    'safehouse-crm:lib/quick-view-navigation',
], function (QuickViewNavigation) {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        process() {
            QuickViewNavigation.ensureEnabled(this.view);
            QuickViewNavigation.patchKanbanLinkClick(this.view);
            QuickViewNavigation.bindAfterSaveRefresh(this.view);

            return Promise.resolve();
        }
    }

    return Handler;
});
