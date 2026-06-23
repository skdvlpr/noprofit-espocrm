define('nonprofit-espocrm:views/export/modals/export', [
    'views/export/modals/export',
], function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            const formatList = this.getFormatList();

            if (this.options.defaultFormat) {
                this.model.set('format', this.options.defaultFormat);
            }

            this.clearView('record');
            this.createView('record', 'nonprofit-espocrm:views/export/record/record', {
                scope: this.scope,
                model: this.model,
                selector: '.record',
                formatList: formatList,
            });

            this.adjustHeaderForEmailFormat();
        },

        getFormatList() {
            return this.getMetadata().get(['scopes', this.scope, 'exportFormatList']) ||
                this.getMetadata().get('app.export.formatList');
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
