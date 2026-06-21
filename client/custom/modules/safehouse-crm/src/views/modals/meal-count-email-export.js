define('safehouse-crm:views/modals/meal-count-email-export', [
    'views/modal',
    'model',
], function (Dep, Model) {

    return Dep.extend({

        className: 'dialog dialog-record',

        templateContent: `
            <div class="record">{{{record}}}</div>
        `,

        setup() {
            this.headerText = this.translate('reportingEmailExport', 'labels', 'Global');

            this.model = new Model();
            this.model.name = 'MealCountEmailExport';
            this.model.set({
                format: 'csv',
                includeTotals: true,
                emailAddressList: [this.getUser().get('emailAddress')].filter(Boolean),
            });

            this.model.defs = {
                fields: {
                    format: {
                        type: 'enum',
                        options: ['csv', 'xlsx'],
                        required: true,
                    },
                    includeTotals: {
                        type: 'bool',
                    },
                    emailAddressList: {
                        type: 'varchar',
                        required: true,
                        maxLength: 255,
                    },
                },
            };

            this.createView('record', 'views/record/edit', {
                model: this.model,
                selector: '.record',
                detailLayout: [
                    {
                        rows: [
                            [{name: 'format'}, false],
                            [{name: 'includeTotals'}, false],
                            [{name: 'emailAddressList'}, false],
                        ],
                    },
                ],
                buttonsDisabled: true,
                sideView: false,
                bottomView: false,
            });

            this.buttonList = [
                {
                    name: 'send',
                    label: 'Send',
                    style: 'primary',
                    onClick: () => this.actionSend(),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: () => this.close(),
                },
            ];
        },

        actionSend() {
            const recordView = this.getView('record');

            if (recordView && recordView.validate()) {
                return;
            }

            const emailRaw = this.model.get('emailAddressList') || '';
            const emailAddressList = String(emailRaw)
                .split(/[,;\s]+/)
                .map(item => item.trim())
                .filter(item => item.length > 0);

            if (!emailAddressList.length) {
                Espo.Ui.error(this.translate('validationFailure', 'messages'));

                return;
            }

            const payload = {
                ...this.options.searchPayload,
                format: this.model.get('format'),
                includeTotals: this.model.get('includeTotals'),
                emailAddressList: emailAddressList,
            };

            Espo.Ui.notifyWait();

            Espo.Ajax.postRequest('SafehouseCrm/reporting/meal-count/email-export', payload)
                .then(() => {
                    Espo.Ui.success(this.translate('reportingEmailExportSuccess', 'labels', 'Global'));
                    this.close();
                })
                .catch(xhr => {
                    Espo.Ui.error(xhr.responseJSON?.messageTranslation || xhr.responseJSON?.message || 'Error');
                });
        },
    });
});
