define('nonprofit-espocrm:views/food-parcel-registration/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync', () => {
                if (this.isRendered()) {
                    this.trigger('panel:pdfPreview:refresh');
                }
            });
        },
    });
});
