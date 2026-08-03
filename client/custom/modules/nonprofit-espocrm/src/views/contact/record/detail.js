define('nonprofit-espocrm:views/contact/record/detail', ['views/record/detail'], function (Dep) {

    return Dep.extend({

        getContactRemoveConfirmationMessage(model) {
            const hasLinkedUser = !!(
                model.get('linkedUserId') ||
                model.get('portalUserId') ||
                model.get('isUser')
            );

            if (hasLinkedUser) {
                return this.translate(
                    'removeRecordConfirmationContactOnly',
                    'messages',
                    'Contact'
                );
            }

            return this.translate('removeRecordConfirmation', 'messages', this.scope);
        },

        /**
         * Delete Contact only. Warn when a CRM/portal user is linked —
         * the User record is not removed.
         */
        async delete() {
            await this.confirm({
                message: this.getContactRemoveConfirmationMessage(this.model),
                confirmText: this.translate('Remove'),
            });

            const originalConfirm = this.confirm;

            this.confirm = async () => {};

            try {
                await Dep.prototype.delete.call(this);
            }
            finally {
                this.confirm = originalConfirm;
            }
        },
    });
});
