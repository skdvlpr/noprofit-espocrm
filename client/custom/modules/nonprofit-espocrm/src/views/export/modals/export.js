define('nonprofit-espocrm:views/export/modals/export', [
    'views/export/modals/export',
    'model',
], function (Dep, Model) {

    return Dep.extend({

        setup() {
            this.buttonList = [
                {
                    name: 'export',
                    label: 'Export',
                    style: 'danger',
                    title: 'Ctrl+Enter',
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                },
            ];

            this.model = new Model();
            this.model.name = 'Export';
            this.scope = this.options.scope;

            if (this.options.fieldList) {
                const fieldList = this.options.fieldList.filter(field => {
                    const defs = this.getMetadata()
                        .get(`entityDefs.${this.scope}.fields.${field}`) || {};

                    return !defs.exportDisabled && !defs.utility;
                });

                this.model.set('fieldList', fieldList);
                this.model.set('exportAllFields', false);
            }
            else {
                this.model.set('exportAllFields', true);
            }

            const formatList = this.getFormatList();
            const preferred = this.options.defaultFormat;
            const initialFormat = (
                preferred && formatList.includes(preferred)
            ) ? preferred : formatList[0];

            this.model.set('format', initialFormat);

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
