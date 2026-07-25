define('nonprofit-espocrm:views/fields/donation-payment-provider', [
    'views/fields/enum',
], function (Dep) {

    /**
     * Enum picklist for payment platform.
     * Stripe is shown for existing ingest rows but hidden on manual create.
     */
    return Dep.extend({

        setup: function () {
            Dep.prototype.setup.call(this);
            this.applyCreateOptions();
        },

        applyCreateOptions: function () {
            const all = this.getMetadata()
                .get(['entityDefs', 'PrimaNota', 'fields', 'donationPaymentProvider', 'options']) || [];

            if (this.model.isNew()) {
                this.params.options = all.filter(option => option !== 'Stripe');
            } else {
                this.params.options = all.slice();
            }
        },
    });
});
