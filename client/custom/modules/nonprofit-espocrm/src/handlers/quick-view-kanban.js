define('nonprofit-espocrm:handlers/quick-view-kanban', [
    'nonprofit-espocrm:lib/quick-view-navigation',
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
