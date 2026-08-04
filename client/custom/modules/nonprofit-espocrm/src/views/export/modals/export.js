define('nonprofit-espocrm:views/export/modals/export', [
    'views/export/modals/export',
], function (Dep) {

    const EMAIL_FORMATS = ['xlsx-email', 'csv-email'];

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            const formatList = this.getFormatList();
            const preferred = this.options.defaultFormat;
            const initialFormat = (
                preferred && formatList.includes(preferred)
            ) ? preferred : this.model.get('format') || formatList[0];

            this.model.set('format', initialFormat);

            // Parent created core export/record; rebuild with our record view
            // and the preferred email format when requested.
            this.clearView('record');
            this.createView('record', 'nonprofit-espocrm:views/export/record/record', {
                scope: this.scope,
                model: this.model,
                selector: '.record',
                formatList: formatList,
            }, view => {
                if (preferred && formatList.includes(preferred)) {
                    this.model.set('format', preferred);

                    if (typeof view.controlFormatField === 'function') {
                        view.controlFormatField();
                    }
                }
            });

            this.adjustHeaderForEmailFormat();
        },

        /**
         * Download modal: xlsx/csv only.
         * Email modal (emailOnly / *-email default): email formats only.
         * Never mix — picking xlsx-email in a download flow used to Save As locally.
         */
        getFormatList() {
            const emailOnly = !!this.options.emailOnly ||
                (
                    this.options.defaultFormat &&
                    String(this.options.defaultFormat).endsWith('-email')
                );

            if (emailOnly) {
                return EMAIL_FORMATS.slice();
            }

            const raw = this.getMetadata().get(['scopes', this.scope, 'exportFormatList']) ||
                this.getMetadata().get('app.export.formatList') ||
                ['xlsx', 'csv'];

            return raw.filter(format => !String(format).endsWith('-email'));
        },

        adjustHeaderForEmailFormat() {
            const format = this.model.get('format');

            if (format !== 'csv-email' && format !== 'xlsx-email') {
                return;
            }

            this.headerText = this.translate('reportingEmailExport', 'labels', 'Global');

            const exportButton = this.buttonList.find(item => item.name === 'export');

            if (exportButton) {
                exportButton.label = 'Send';
                exportButton.style = 'primary';
            }
        },
    });
});
