define('nonprofit-espocrm:handlers/quick-view-list', [
    'nonprofit-espocrm:lib/quick-view-navigation',
], function (QuickViewNavigation) {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        process() {
            if (!QuickViewNavigation.isRelationshipList(this.view)) {
                return Promise.resolve();
            }

            // Planner (and any scope with quickDetailDisabled) stays full-page.
            if (QuickViewNavigation.applyQuickDetailPolicy(this.view) === 'quick') {
                QuickViewNavigation.patchListLinkClick(this.view);
            }

            QuickViewNavigation.bindAfterSaveRefresh(this.view);

            return Promise.resolve();
        }
    }

    return Handler;
});
