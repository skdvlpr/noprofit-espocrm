define('nonprofit-espocrm:handlers/quick-view-list', [
    'nonprofit-espocrm:lib/quick-view-navigation',
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
