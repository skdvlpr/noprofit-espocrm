define('nonprofit-espocrm:handlers/prima-nota/copy-payment-data', [], function () {

    const CRM_FIELD_KEYS = [
        'id',
        'name',
        'description',
        'transactionDate',
        'entryType',
        'amount',
        'amountCurrency',
        'amountGross',
        'amountGrossCurrency',
        'commissionAmount',
        'commissionAmountCurrency',
        'commissionPercent',
        'internalClassification',
        'modelDClassification',
        'donationPaymentProvider',
        'donationPaymentReference',
        'donationDonorCategory',
        'donationFrequency',
        'donationComment',
        'financingId',
        'financingName',
        'subjectName',
        'subjectPartyId',
        'subjectPartyType',
        'subjectPartyName',
        'beneficiaryName',
        'beneficiaryPartyId',
        'beneficiaryPartyType',
        'beneficiaryPartyName',
        'assignedUserId',
        'assignedUserName',
    ];

    const STRIPE_FIELD_KEYS = [
        'stripePaymentCreatedAt',
        'stripeChargeId',
        'stripeBalanceTransactionId',
        'stripePaymentMethodType',
        'stripeCardBrand',
        'stripeCardLast4',
        'stripeReceiptUrl',
        'stripeReceiptEmail',
        'stripeBillingEmail',
        'stripeBillingPhone',
        'stripeFeeDetailsJson',
        'stripeLivemode',
        'stripeRadarRiskLevel',
        'stripeStatementDescriptor',
        'stripeCustomerId',
    ];

    class Handler {

        constructor(view) {
            this.view = view;
        }

        copy() {
            const model = this.resolveModel();

            if (!model) {
                Espo.Ui.error(this.view.translate('copyPaymentDataFailed', 'messages', 'PrimaNota'));

                return;
            }

            const payload = {
                crm_fields: this.pickFields(model, CRM_FIELD_KEYS),
                stripe_fields: this.pickFields(model, STRIPE_FIELD_KEYS),
            };

            const text = JSON.stringify(payload, null, 2);

            this.writeClipboard(text)
                .then(() => {
                    Espo.Ui.success(this.view.translate('copyPaymentDataCopied', 'messages', 'PrimaNota'));
                })
                .catch(() => {
                    Espo.Ui.error(this.view.translate('copyPaymentDataFailed', 'messages', 'PrimaNota'));
                });
        }

        resolveModel() {
            const view = this.view;

            if (view && view.model) {
                return view.model;
            }

            if (view && typeof view.getRecordView === 'function') {
                const recordView = view.getRecordView();

                if (recordView && recordView.model) {
                    return recordView.model;
                }
            }

            return null;
        }

        pickFields(model, keys) {
            const out = {};

            keys.forEach(key => {
                const value = model.get(key);

                if (value === null || typeof value === 'undefined' || value === '') {
                    return;
                }

                out[key] = value;
            });

            return out;
        }

        writeClipboard(text) {
            if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
                return navigator.clipboard.writeText(text);
            }

            return new Promise((resolve, reject) => {
                try {
                    const area = document.createElement('textarea');
                    area.value = text;
                    area.setAttribute('readonly', '');
                    area.style.position = 'fixed';
                    area.style.left = '-9999px';
                    document.body.appendChild(area);
                    area.select();
                    const ok = document.execCommand('copy');
                    document.body.removeChild(area);

                    if (ok) {
                        resolve();
                    } else {
                        reject(new Error('copy failed'));
                    }
                } catch (e) {
                    reject(e);
                }
            });
        }
    }

    return Handler;
});
