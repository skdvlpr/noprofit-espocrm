define('nonprofit-espocrm:handlers/prima-nota/refresh-from-stripe', [], function () {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        refresh() {
            const model = this.resolveModel();

            if (!model || !model.id) {
                Espo.Ui.error(this.view.translate('refreshFromStripeFailed', 'messages', 'PrimaNota'));

                return;
            }

            if (model.get('donationPaymentProvider') !== 'Stripe') {
                Espo.Ui.error(this.view.translate('refreshFromStripeNotStripe', 'messages', 'PrimaNota'));

                return;
            }

            Espo.Ui.notify(this.view.translate('refreshFromStripeInProgress', 'messages', 'PrimaNota'));

            Espo.Ajax.postRequest('PrimaNota/action/refreshFromStripe', {id: model.id})
                .then(response => {
                    return model.fetch().then(() => response);
                })
                .then(response => {
                    const status = response && response.paymentStatus
                        ? String(response.paymentStatus)
                        : (model.get('paymentStatus') || '');

                    Espo.Ui.success(
                        this.view.translate('refreshFromStripeSuccess', 'messages', 'PrimaNota')
                            .replace('{status}', status)
                    );
                })
                .catch(xhr => {
                    let message = this.view.translate('refreshFromStripeFailed', 'messages', 'PrimaNota');

                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        message = String(xhr.responseJSON.message);
                    }
                    else if (xhr && xhr.getResponseHeader) {
                        const reason = xhr.getResponseHeader('X-Status-Reason');

                        if (reason) {
                            message = reason;
                        }
                    }

                    Espo.Ui.error(message);
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
    }

    return Handler;
});
