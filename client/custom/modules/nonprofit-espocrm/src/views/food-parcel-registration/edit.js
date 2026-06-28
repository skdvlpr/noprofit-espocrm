define('nonprofit-espocrm:views/food-parcel-registration/edit', [
    'views/record/edit',
    'nonprofit-espocrm:views/food-parcel-registration/record-contact-sync',
], function (Dep, RecordContactSync) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            RecordContactSync.setupContactIdentitySync.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            RecordContactSync.afterRenderContactIdentitySync.call(this);
        },
    });
});
