define('safehouse-crm:handlers/quick-view-list', [
    'safehouse-crm:lib/quick-view-navigation',
], function (QuickViewNavigation) {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        process() {
            QuickViewNavigation.ensureEnabled(this.view);
            QuickViewNavigation.patchListLinkClick(this.view);
            QuickViewNavigation.bindAfterSaveRefresh(this.view);

            return Promise.resolve();
        }
    }

    return Handler;
});
