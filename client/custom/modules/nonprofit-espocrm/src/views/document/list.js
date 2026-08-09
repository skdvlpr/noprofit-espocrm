define('nonprofit-espocrm:views/document/list', [
    'crm:views/document/list',
    'nonprofit-espocrm:lib/quick-view-navigation',
], function (Dep, QuickViewNavigation) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.apply(this, arguments);

            if (QuickViewNavigation.isRelationshipList(this)) {
                if (QuickViewNavigation.applyQuickDetailPolicy(this) === 'quick') {
                    QuickViewNavigation.patchListLinkClick(this);
                }
            }

            QuickViewNavigation.bindAfterSaveRefresh(this);
        },
    });
});
