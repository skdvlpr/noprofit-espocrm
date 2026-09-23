define('nonprofit-espocrm:views/lead/panels/pdf-preview', ['views/record/panels/bottom'], function (Dep) {

    /**
     * Same preview chrome as the food-parcel registration PDF panel.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/printing-to-pdf.md
     */
    return Dep.extend({

        templateContent: `
            <div class="food-parcel-pdf-preview">
                <div class="button-container margin-bottom-sm">
                    <button type="button" class="btn btn-default btn-xs" data-action="refreshPdf">{{refreshLabel}}</button>
                    <a class="btn btn-default btn-xs" data-role="openPdf" target="_blank" rel="noopener">{{openLabel}}</a>
                </div>
                <iframe data-name="pdfFrame" class="food-parcel-pdf-frame" title="PDF preview"></iframe>
            </div>
        `,

        events: {
            'click [data-action="refreshPdf"]': function () {
                this.loadPdf();
            },
        },

        setup() {
            Dep.prototype.setup.call(this);
            this.listenTo(this.model, 'sync', () => this.loadPdf());
        },

        data() {
            return {
                refreshLabel: this.translate('Refresh', 'labels', 'Global'),
                openLabel: this.translate('openPdfPreview', 'labels', 'Lead'),
            };
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (!this.isAssociato()) {
                this.$el.empty();

                return;
            }

            this.loadPdf();
        },

        isAssociato() {
            const types = this.model.get('contactType') || [];

            return Array.isArray(types) && types.indexOf('MemberContact') !== -1;
        },

        loadPdf() {
            if (!this.model.id || !this.isAssociato()) {
                return;
            }

            const url = this.getPdfUrl();
            const frame = this.$el.find('[data-name="pdfFrame"]');

            frame.attr('src', url + '?t=' + Date.now());
            this.$el.find('[data-role="openPdf"]').attr('href', url);
        },

        getPdfUrl() {
            const siteUrl = (this.getConfig().get('siteUrl') || window.location.origin).replace(/\/$/, '');

            return siteUrl
                + '/api/v1/NonprofitEspocrm/lead/'
                + encodeURIComponent(this.model.id)
                + '/admission-pdf';
        },
    });
});
