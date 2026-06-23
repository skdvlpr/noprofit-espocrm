define('nonprofit-espocrm:views/document/list', [
    'crm:views/document/list',
    'nonprofit-espocrm:lib/quick-view-navigation',
], function (Dep, QuickViewNavigation) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.apply(this, arguments);
            QuickViewNavigation.ensureEnabled(this);
            QuickViewNavigation.patchListLinkClick(this);
            QuickViewNavigation.bindAfterSaveRefresh(this);
        },
    });
});
