define('nonprofit-espocrm:views/lead/record/edit-small', [
    'nonprofit-espocrm:views/lead/record/edit',
], function (Dep) {

    /**
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     */
    return Dep.extend({

        type: 'editSmall',
        layoutName: 'detailSmall',
    });
});
