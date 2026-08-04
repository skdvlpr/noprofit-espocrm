define('nonprofit-espocrm:lib/reporting-list-export', [
    'helpers/export',
], function (ExportHelper) {

    const EMAIL_FORMATS = new Set(['csv-email', 'xlsx-email']);

    const isEmailExportFormat = format => EMAIL_FORMATS.has(format);

    /**
     * Mixin for reporting entity list views: native export modal + email formats.
     *
     * @type {Object}
     */
    return {

        exportModalView: 'nonprofit-espocrm:views/export/modals/export',

        /**
         * Opens the native export modal with an email format pre-selected.
         *
         * @param {string} [defaultFormat='xlsx-email']
         */
        openReportingEmailExport(defaultFormat = 'xlsx-email') {
            this.export(null, null, null, {
                defaultFormat: defaultFormat,
                emailOnly: true,
            });
        },

        /**
         * @param {Object<string,*>} [data]
         * @param {string} [url]
         * @param {string[]} [fieldList]
         * @param {{defaultFormat?: string, emailOnly?: boolean}} [options]
         */
        export(data, url, fieldList, options) {
            options = options || {};

            if (!data) {
                data = {
                    entityType: this.entityType,
                };

                if (this.allResultIsChecked) {
                    data.where = this.getWhereForAllResult();
                    data.searchParams = this.collection.data || null;
                    data.searchData = this.collection.data || {};
                } else if (this.checkedList && this.checkedList.length) {
                    data.ids = this.checkedList.slice();
                }
            }

            url = url || 'Export';

            const modalOptions = {
                scope: this.entityType,
                defaultFormat: options.defaultFormat,
                emailOnly: !!options.emailOnly ||
                    (options.defaultFormat && String(options.defaultFormat).endsWith('-email')),
            };

            if (fieldList) {
                modalOptions.fieldList = fieldList;
            } else {
                const layoutFieldList = [];

                (this.listLayout || []).forEach(item => {
                    if (item.name) {
                        layoutFieldList.push(item.name);
                    }
                });

                modalOptions.fieldList = layoutFieldList;
            }

            const helper = new ExportHelper(this);
            const idle = this.allResultIsChecked && helper.checkIsIdle(this.collection.total);

            const proceedDownload = attachmentId => {
                window.location = `${this.getBasePath()}?entryPoint=download&id=${attachmentId}`;
            };

            this.createView('dialogExport', this.exportModalView, modalOptions, view => {
                view.render();

                this.listenToOnce(view, 'proceed', dialogData => {
                    if (!dialogData.exportAllFields) {
                        data.attributeList = dialogData.attributeList;
                        data.fieldList = dialogData.fieldList;
                    }

                    data.idle = idle;
                    data.format = dialogData.format;
                    data.params = dialogData.params;

                    if (isEmailExportFormat(dialogData.format)) {
                        this.postReportingEmailExport(data);

                        return;
                    }

                    Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

                    Espo.Ajax.postRequest(url, data, {
                        timeout: 0,
                    }).then(response => {
                        Espo.Ui.notify(false);

                        if (response.exportId) {
                            helper.process(response.exportId).then(idleView => {
                                this.listenToOnce(idleView, 'download', id => proceedDownload(id));
                            });

                            return;
                        }

                        if (!response.id) {
                            throw new Error('No attachment-id.');
                        }

                        proceedDownload(response.id);
                    });
                });
            });
        },

        /**
         * @param {Object<string, *>} data
         */
        postReportingEmailExport(data) {
            Espo.Ui.notifyWait();

            Espo.Ajax.postRequest('NonprofitEspocrm/reporting/email-export', data, {
                timeout: 0,
            })
                .then(() => {
                    Espo.Ui.success(this.translate('reportingEmailExportSuccess', 'labels', 'Global'));
                })
                .catch(xhr => {
                    Espo.Ui.error(
                        xhr.responseJSON?.messageTranslation ||
                        xhr.responseJSON?.message ||
                        'Error'
                    );
                });
        },
    };
});
