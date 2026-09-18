define('nonprofit-espocrm:views/contact/record/edit', ['views/record/edit'], function (Dep) {

    /**
     * Contact-first CRM user: Crea utente CRM on create only. Save opens a
     * review dialog-record; confirm POSTs Contact then User. Checkbox MUST
     * NOT open the panel.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/terms-and-naming.md
     */
    return Dep.extend({

        layoutName: 'edit',

        setup() {
            Dep.prototype.setup.call(this);

            this._crmUserDraft = null;
            this._crmUserReviewConfirmed = false;
            this._openingCrmUserReview = false;

            if (!this.model.isNew()) {
                this.model.set('createCrmUser', false);
            }

            this.listenTo(this.model, 'change:createCrmUser', () => {
                this.syncLinkedUserPickerVisibility();
            });

            this.listenTo(this.model, 'change:contactType', () => {
                this.syncLinkedUserPickerVisibility();
            });

            this.once('after:render', () => {
                this.restrictPersonnelTypeOptions();
                this.syncCreateCrmUserFieldVisibility();
                this.syncLinkedUserPickerVisibility();
            });
        },

        isPersonnelType() {
            const type = this.model.get('contactType');

            return type === 'Volunteer' || type === 'Employee';
        },

        restrictPersonnelTypeOptions() {
            if (this.getUser().isAdmin()) {
                return;
            }

            if (!this.model.isNew()) {
                return;
            }

            const fieldView = this.getFieldView('contactType');

            if (!fieldView) {
                return;
            }

            const current = fieldView.params && fieldView.params.options ?
                fieldView.params.options :
                [];
            const options = current.filter(value => value !== 'Volunteer' && value !== 'Employee');

            if (typeof fieldView.setOptionList === 'function') {
                fieldView.setOptionList(options);
            }
            else if (fieldView.params) {
                fieldView.params.options = options;
            }
        },

        syncCreateCrmUserFieldVisibility() {
            if (!this.model.isNew()) {
                this.hideField('createCrmUser');

                return;
            }

            if (this.isPersonnelType()) {
                this.showField('createCrmUser');
            }
        },

        syncLinkedUserPickerVisibility() {
            if (
                this.model.isNew() &&
                this.model.get('createCrmUser') &&
                this.isPersonnelType()
            ) {
                this.hideField('linkedUser');
                this.model.set({
                    linkedUserId: null,
                    linkedUserName: null,
                });

                return;
            }

            this.showField('linkedUser');
        },

        copiedIdentityFromContact() {
            const identity = {
                salutationName: this.model.get('salutationName') || null,
                firstName: this.model.get('firstName') || null,
                lastName: this.model.get('lastName') || null,
                emailAddress: this.model.get('emailAddress') || null,
                phoneNumber: this.model.get('phoneNumber') || null,
                emailAddressData: this.model.get('emailAddressData') || null,
                phoneNumberData: this.model.get('phoneNumberData') || null,
            };

            [
                'isOccasional',
                'startDate',
                'endDate',
                'weeklyHours',
                'monthlyHours',
                'extra',
                'taxCode',
                'birthDate',
                'birthPlace',
                'birthProvince',
                'activityCompetences',
            ].forEach(field => {
                const value = this.model.get(field);

                if (value === undefined) {
                    return;
                }

                identity[field] = value;
            });

            return identity;
        },

        shouldOfferCrmUserReview() {
            if (!this.model.isNew()) {
                return false;
            }

            if (!this.model.get('createCrmUser')) {
                return false;
            }

            if (!this.isPersonnelType()) {
                return false;
            }

            if (this.model.get('linkedUserId')) {
                return false;
            }

            return true;
        },

        buildUserAttributes() {
            const identity = this.copiedIdentityFromContact();
            const email = identity.emailAddress || '';
            let userName = null;

            if (this._crmUserDraft && this._crmUserDraft.userName) {
                userName = this._crmUserDraft.userName;
            }
            else if (email.includes('@')) {
                userName = email.split('@')[0].replace(/[^a-zA-Z0-9._-]/g, '') || null;
            }

            const base = this._crmUserDraft ? Espo.Utils.cloneDeep(this._crmUserDraft) : {
                type: 'regular',
                isActive: true,
                sendAccessInfo: !!identity.emailAddress,
            };

            return Object.assign(base, identity, {
                userName: userName,
                type: 'regular',
                sendAccessInfo: base.sendAccessInfo !== false && !!identity.emailAddress,
            });
        },

        fetchPersonnelRoleId() {
            const wanted = this.model.get('contactType') === 'Employee' ? 'Employee' : 'Volunteer';

            return Espo.Ajax.getRequest('Role', {
                maxSize: 50,
                select: 'id,name',
            }).then(response => {
                const list = (response && response.list) ? response.list : [];
                const hit = list.find(row => row.name === wanted);

                return hit ? hit.id : null;
            });
        },

        openCreateCrmUserReview() {
            if (this.getView('createCrmUser') || this._openingCrmUserReview) {
                return Promise.resolve();
            }

            this._openingCrmUserReview = true;

            return this.fetchPersonnelRoleId()
                .catch(() => null)
                .then(roleId => {
                const attributes = this.buildUserAttributes();

                if (roleId && (!attributes.rolesIds || !attributes.rolesIds.length)) {
                    attributes.rolesIds = [roleId];
                    attributes.rolesNames = attributes.rolesNames || {};
                    attributes.rolesNames[roleId] = this.model.get('contactType') === 'Employee' ?
                        'Employee' : 'Volunteer';
                }

                return new Promise(resolve => {
                    this.createView('createCrmUser', 'nonprofit-espocrm:views/modals/create-crm-user', {
                        scope: 'User',
                        attributes: attributes,
                        fullFormDisabled: true,
                        headerText: this.translate('Create User', 'labels', 'User'),
                        onConfirm: confirmed => {
                            this._crmUserDraft = confirmed;
                            this._crmUserReviewConfirmed = true;
                            this._openingCrmUserReview = false;
                            resolve('confirmed');
                        },
                        onCancel: () => {
                            this._crmUserDraft = null;
                            this._crmUserReviewConfirmed = false;
                            this._openingCrmUserReview = false;
                            resolve('cancelled');
                        },
                    }, view => {
                        view.render();
                    });
                });
            });
        },

        buildUserPostPayload() {
            const payload = Object.assign(
                {},
                Espo.Utils.cloneDeep(this._crmUserDraft || {}),
                this.copiedIdentityFromContact(),
                {
                    sourceContactId: this.model.id,
                    type: 'regular',
                }
            );

            delete payload.password;
            delete payload.passwordConfirm;
            delete payload.passwordPreview;
            delete payload.passwordInfo;
            delete payload.generatePassword;

            [
                'id',
                'createdAt',
                'modifiedAt',
                'deleted',
            ].forEach(key => {
                delete payload[key];
            });

            Object.keys(payload).forEach(key => {
                if (payload[key] === null || payload[key] === undefined) {
                    delete payload[key];
                }
            });

            return payload;
        },

        removeJustCreatedContact() {
            const id = this.model.id;

            if (!id) {
                return Promise.resolve();
            }

            return Espo.Ajax.deleteRequest('Contact/' + id).catch(() => {});
        },

        createLinkedUserIfNeeded() {
            if (!this._crmUserReviewConfirmed || !this._crmUserDraft || !this.model.id) {
                return Promise.resolve();
            }

            const payload = this.buildUserPostPayload();

            return Espo.Ajax.postRequest('User', payload)
                .then(() => {
                    this._crmUserDraft = null;
                    this._crmUserReviewConfirmed = false;
                    this.model.set('createCrmUser', false, {silent: true});

                    return this.model.fetch();
                })
                .catch(xhr => {
                    let error = this.translate('Error occurred', 'messages');

                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        error = xhr.responseJSON.message;
                    }
                    else if (xhr && xhr.responseText) {
                        error = String(xhr.responseText).slice(0, 240);
                    }

                    return this.removeJustCreatedContact().then(() => {
                        Espo.Ui.error(
                            this.translate('createCrmUserFailed', 'messages', 'Contact')
                                .replace('{error}', error)
                        );

                        this.getRouter().navigate('#Contact', {trigger: true});

                        return Promise.reject('user-create-failed');
                    });
                });
        },

        save(options) {
            options = options || {};

            if (this.shouldOfferCrmUserReview() && !this._crmUserReviewConfirmed) {
                if (!this.model.get('emailAddress')) {
                    Espo.Ui.error(this.translate('createCrmUserNeedsEmail', 'messages', 'Contact'));
                    this.afterNotValid();

                    return Promise.reject('invalid');
                }

                return this.openCreateCrmUserReview().then(result => {
                    if (result !== 'confirmed') {
                        return Promise.resolve();
                    }

                    options.skipNotModifiedWarning = true;

                    return Dep.prototype.save.call(this, options).then(
                        () => this.createLinkedUserIfNeeded()
                    );
                });
            }

            if (this._crmUserReviewConfirmed) {
                options.skipNotModifiedWarning = true;
            }

            return Dep.prototype.save.call(this, options).then(
                () => this.createLinkedUserIfNeeded(),
                reason => Promise.reject(reason)
            );
        },
    });
});
