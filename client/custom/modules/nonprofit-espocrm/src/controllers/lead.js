define('nonprofit-espocrm:controllers/lead', ['crm:controllers/lead'], function (Dep) {

    Dep = Dep.default || Dep;

    /**
     * Use the module convert view (stock controller hardcodes crm:views/lead/convert).
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
     */
    return class extends Dep {
        actionConvert(options) {
            this.main('nonprofit-espocrm:views/lead/convert', {
                id: options.id,
            });
        }
    };
});
