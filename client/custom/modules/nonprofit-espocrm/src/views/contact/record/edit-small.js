define('nonprofit-espocrm:views/contact/record/edit-small', [
    'nonprofit-espocrm:views/contact/record/edit',
], function (Dep) {

    /**
     * Quick Contact create uses the same review-modal CRM-user path as full edit.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/metadata/client-defs.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     */
    return Dep.extend({

        type: 'editSmall',
        layoutName: 'detailSmall',
    });
});
