define('nonprofit-espocrm:views/contact/record/list', ['views/record/list'], function (Dep) {

    return Dep.extend({

        mandatorySelectAttributeList: [
            'linkedUserId',
            'portalUserId',
            'isUser',
        ],

        contactHasLinkedUser(model) {
            return !!(
                model.get('linkedUserId') ||
                model.get('portalUserId') ||
                model.get('isUser')
            );
        },

        getContactRemoveConfirmationMessage(model) {
            if (this.contactHasLinkedUser(model)) {
                return this.translate(
                    'removeRecordConfirmationContactOnly',
                    'messages',
                    'Contact'
                );
            }

            return this.translate('removeRecordConfirmation', 'messages', this.scope);
        },

        getContactMassRemoveConfirmationMessage() {
            const ids = this.checkedList || [];

            for (const id of ids) {
                const model = this.collection.get(id);

                if (model && this.contactHasLinkedUser(model)) {
                    return this.translate(
                        'removeSelectedRecordsConfirmationContactOnly',
                        'messages',
                        'Contact'
                    );
                }
            }

            return this.translate(
                'removeSelectedRecordsConfirmation',
                'messages',
                this.scope
            );
        },

        async actionQuickRemove(data) {
            data = data || {};
            const id = data.id;

            if (!id) {
                return;
            }

            const model = this.collection.get(id);

            if (!model) {
                throw new Error('No model.');
            }

            if (!this.getAcl().checkModel(model, 'delete')) {
                Espo.Ui.error(this.translate('Access denied'));

                return;
            }

            await this.confirm({
                message: this.getContactRemoveConfirmationMessage(model),
                confirmText: this.translate('Remove'),
            });

            // Skip parent's confirm dialog; keep its remove/success handling.
            const originalConfirm = this.confirm;

            this.confirm = async () => {};

            try {
                await Dep.prototype.actionQuickRemove.call(this, data);
            }
            finally {
                this.confirm = originalConfirm;
            }
        },

        async massActionRemove() {
            if (!this.getAcl().check(this.entityType, 'delete')) {
                Espo.Ui.error(this.translate('Access denied'));

                return false;
            }

            await this.confirm({
                message: this.getContactMassRemoveConfirmationMessage(),
                confirmText: this.translate('Remove'),
            });

            const originalConfirm = this.confirm;

            this.confirm = async () => {};

            try {
                return await Dep.prototype.massActionRemove.call(this);
            }
            finally {
                this.confirm = originalConfirm;
            }
        },
    });
});
