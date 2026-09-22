define('nonprofit-espocrm:views/lead/record/edit', [
    'views/record/edit',
    'nonprofit-espocrm:helpers/contact-type-set',
], function (Dep, ContactTypeSet) {

    /**
     * Lead type picker: same legal sets as Contact; no extra field panels.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
     */
    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);
            ContactTypeSet.attachToRecordView(this);
        },
    });
});
