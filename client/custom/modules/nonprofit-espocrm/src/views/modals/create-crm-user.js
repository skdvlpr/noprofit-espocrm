define('nonprofit-espocrm:views/modals/create-crm-user', ['views/modals/edit'], function (Dep) {

    /**
     * Review drawer after Contact create Save. Stashes User attributes only.
     * MUST NOT POST User. MUST NOT open from the Crea utente CRM checkbox.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
     */
    return Dep.extend({

        className: 'dialog dialog-record',
        editView: 'nonprofit-espocrm:views/user/record/create-from-contact',
        fullFormDisabled: true,

        setup() {
            this._confirmed = false;

            Dep.prototype.setup.call(this);

            this.listenToOnce(this, 'close', () => {
                if (this._confirmed) {
                    return;
                }

                if (typeof this.options.onCancel === 'function') {
                    this.options.onCancel();
                }
            });
        },

        handleRecordViewOptions(options) {
            options.type = 'edit';
            options.layoutName = 'detail';
        },

        createRecordView(model, callback) {
            const self = this;

            Dep.prototype.createRecordView.call(this, model, function (view) {
                const lock = function () {
                    const rec = self.getRecordView();

                    if (rec && typeof rec.lockCopiedIdentityFields === 'function') {
                        rec.lockCopiedIdentityFields();
                    }

                    if (rec && typeof rec.applyCreateFromContactAccessInfo === 'function') {
                        rec.applyCreateFromContactAccessInfo();
                    }
                };

                lock();

                if (view) {
                    self.listenToOnce(view, 'after:render', lock);
                }

                if (typeof callback === 'function') {
                    callback(view);
                }
            });
        },

        actionSave() {
            const editView = this.getRecordView();

            if (!editView) {
                return;
            }

            const fetched = editView.processFetch();

            if (fetched === null) {
                return;
            }

            const attributes = this.buildStash(editView.model);

            this._confirmed = true;

            if (typeof this.options.onConfirm === 'function') {
                this.options.onConfirm(attributes);
            }

            this.close();
        },

        buildStash(model) {
            const attributes = model.getClonedAttributes();

            delete attributes.password;
            delete attributes.passwordConfirm;
            delete attributes.id;
            delete attributes.passwordPreview;
            delete attributes.passwordInfo;
            delete attributes.generatePassword;

            return attributes;
        },
    });
});
