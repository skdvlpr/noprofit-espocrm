define('nonprofit-espocrm:views/user/record/create-from-contact', [
    'nonprofit-espocrm:views/user/record/edit',
], function (Dep) {

    /**
     * Review-panel User form: identity locked; password fields never shown;
     * sendAccessInfo is the set-password-link invite (custom label).
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     */
    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.once('after:render', () => {
                this.lockCopiedIdentityFields();
                this.applyCreateFromContactAccessInfo();
            });
        },

        lockCopiedIdentityFields() {
            ['salutationName', 'firstName', 'lastName', 'name', 'emailAddress', 'phoneNumber', 'type']
                .forEach(field => {
                    if (typeof this.setFieldReadOnly === 'function') {
                        this.setFieldReadOnly(field);
                    }
                });
        },

        applyCreateFromContactAccessInfo() {
            this.hideField('password');
            this.hideField('passwordConfirm');
            this.hideField('generatePassword');
            this.hideField('passwordPreview');
            this.hideField('passwordInfo');
            this.model.set(
                {
                    password: null,
                    passwordConfirm: null,
                },
                {silent: true}
            );

            if (!this.model.get('emailAddress')) {
                this.hideField('sendAccessInfo');
                this.model.set('sendAccessInfo', false);

                return;
            }

            this.showField('sendAccessInfo');

            if (!this.model.get('sendAccessInfo')) {
                this.model.set('sendAccessInfo', true);
            }

            this.applyInviteCheckboxLabel();
        },

        applyInviteCheckboxLabel() {
            const fieldView = this.getFieldView('sendAccessInfo');
            const label = this.translate('sendPasswordCreateLink', 'labels', 'Contact');

            if (!fieldView || !label || label === 'sendPasswordCreateLink') {
                return;
            }

            fieldView.labelText = label;

            const $label = fieldView.$el && fieldView.$el.closest('.cell').find('label').first();

            if ($label && $label.length) {
                $label.text(label);
            }
        },
    });
});
