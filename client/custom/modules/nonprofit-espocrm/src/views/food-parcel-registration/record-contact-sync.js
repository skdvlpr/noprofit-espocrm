define('nonprofit-espocrm:views/food-parcel-registration/record-contact-sync', [], function () {

    const IDENTITY_FIELD_NAMES = ['taxCode', 'phone', 'address'];

    const formatContactPhones = contact => {
        const rows = contact.phoneNumberData;

        if (Array.isArray(rows) && rows.length) {
            const lines = rows
                .map(row => {
                    const number = (row.phoneNumber || '').trim();

                    if (!number) {
                        return null;
                    }

                    const type = (row.type || '').trim();

                    return type ? `${number} (${type})` : number;
                })
                .filter(line => line !== null);

            if (lines.length) {
                return lines.join('\n');
            }
        }

        const fallback = (contact.phoneNumber || '').trim();

        return fallback || null;
    };

    return {

        setupContactIdentitySync() {
            this.listenTo(this.model, 'change:contactId', () => {
                clearTimeout(this._contactSyncTimeout);

                this._contactSyncTimeout = setTimeout(() => {
                    this.syncContactIdentityFromContact();
                }, 0);
            });
        },

        afterRenderContactIdentitySync() {
            if (this.model.get('contactId')) {
                setTimeout(() => {
                    this.syncContactIdentityFromContact();
                }, 0);
            }
        },

        reRenderIdentityFields() {
            IDENTITY_FIELD_NAMES.forEach(name => {
                const view = this.getFieldView(name);

                if (view && view.isRendered && view.isRendered()) {
                    view.reRender();
                }
            });
        },

        syncContactIdentityFromContact() {
            const contactId = this.model.get('contactId');

            if (!contactId) {
                this.model.set({
                    taxCode: null,
                    phone: null,
                    addressStreet: null,
                    addressCity: null,
                    addressState: null,
                    addressCountry: null,
                    addressPostalCode: null,
                }, {ui: true, skipReRender: true});

                this.reRenderIdentityFields();

                return;
            }

            Espo.Ajax.getRequest('Contact/' + contactId).then(contact => {
                this.model.set({
                    taxCode: contact.taxCode || null,
                    phone: formatContactPhones(contact),
                    addressStreet: contact.addressStreet || null,
                    addressCity: contact.addressCity || null,
                    addressState: contact.addressState || null,
                    addressCountry: contact.addressCountry || null,
                    addressPostalCode: contact.addressPostalCode || null,
                }, {ui: true, skipReRender: true});

                this.reRenderIdentityFields();
            });
        },
    };
});
