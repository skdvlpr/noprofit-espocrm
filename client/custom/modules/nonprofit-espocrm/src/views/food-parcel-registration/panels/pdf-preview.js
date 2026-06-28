define('nonprofit-espocrm:views/food-parcel-registration/panels/pdf-preview', ['views/record/panels/side'], function (Dep) {

    return Dep.extend({

        templateContent: `
            <div class="food-parcel-pdf-preview">
                <div class="button-container margin-bottom">
                    <button type="button" class="btn btn-default btn-xs" data-action="refreshPdf">{{refreshLabel}}</button>
                    <a class="btn btn-default btn-xs" data-role="openPdf" target="_blank" rel="noopener">{{openLabel}}</a>
                </div>
                <iframe data-name="pdfFrame" class="food-parcel-pdf-frame" title="PDF preview"></iframe>
            </div>
        `,

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'sync', () => this.loadPdf());
        },

        data() {
            return {
                refreshLabel: this.translate('Refresh', 'labels', 'Global'),
                openLabel: this.translate('openPdfPreview', 'labels', 'FoodParcelRegistration'),
            };
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.loadPdf();
        },

        loadPdf() {
            if (!this.model.id) {
                return;
            }

            const url = this.getPdfUrl();
            const $frame = this.$iframe || this.$el.find('[data-name="pdfFrame"]');
            this.$iframe = $frame;

            $frame.attr('src', url + '?t=' + Date.now());

            this.$el.find('[data-role="openPdf"]').attr('href', url);
        },

        getPdfUrl() {
            return 'NonprofitEspocrm/food-parcel-registration/' + this.model.id + '/pdf';
        },

        refreshPdf() {
            this.loadPdf();
        },
    });
});
