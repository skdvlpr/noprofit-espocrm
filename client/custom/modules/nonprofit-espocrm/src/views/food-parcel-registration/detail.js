define('nonprofit-espocrm:views/food-parcel-registration/detail', [
    'views/record/detail',
    'nonprofit-espocrm:views/food-parcel-registration/record-contact-sync',
], function (Dep, RecordContactSync) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync', () => {
                if (this.isRendered()) {
                    this.trigger('panel:pdfPreview:refresh');
                }
            });

            RecordContactSync.setupContactIdentitySync.call(this);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            RecordContactSync.afterRenderContactIdentitySync.call(this);
        },
    });
});
