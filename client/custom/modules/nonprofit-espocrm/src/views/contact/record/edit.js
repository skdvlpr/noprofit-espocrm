define('nonprofit-espocrm:views/contact/record/edit', [
    'views/record/edit',
    'nonprofit-espocrm:helpers/contact-type-set',
], function (Dep, ContactTypeSet) {

    /**
     * Contact-first CRM user: Crea utente CRM on create only. Save opens a
     * review dialog-record; confirm POSTs Contact then User. Checkbox MUST
     * NOT open the panel. Multi-type: Volunteer+Member / Employee+Member.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/users-management.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/passwords.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/acl.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/view.md
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

            ContactTypeSet.attachToRecordView(this, function () {
                this.syncCreateCrmUserFieldVisibility();
                this.syncLinkedUserPickerVisibility();
            });
        },

        contactTypeList() {
            return ContactTypeSet.list(this.model.get('contactType'));
        },

        wantsCrmUser() {
            return ContactTypeSet.wantsCrmUser(this.contactTypeList());
        },

        syncCreateCrmUserFieldVisibility() {
            if (!this.model.isNew()) {
                this.hideField('createCrmUser');

                return;
            }

            if (this.wantsCrmUser()) {
                this.showField('createCrmUser');

                return;
            }

            this.hideField('createCrmUser');
        },

        syncLinkedUserPickerVisibility() {
            if (
                this.model.isNew() &&
                this.model.get('createCrmUser') &&
                this.wantsCrmUser()
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
                'joinDate',
                'leaveDate',
                'positionsHeld',
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

            if (!this.wantsCrmUser()) {
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

        fetchMatchingRoles() {
            const wanted = ContactTypeSet.roleNames(this.contactTypeList());

            return Espo.Ajax.getRequest('Role', {
                maxSize: 200,
                select: 'id,name',
            }).then(response => {
                const list = (response && response.list) ? response.list : [];
                const rolesIds = [];
                const rolesNames = {};

                wanted.forEach(name => {
                    const hit = list.find(row => row.name === name);

                    if (hit) {
                        rolesIds.push(hit.id);
                        rolesNames[hit.id] = name;
                    }
                });

                return {rolesIds: rolesIds, rolesNames: rolesNames};
            });
        },

        releaseCreateCrmUserReview() {
            ContactTypeSet.dropCreateCrmUserReview(this);
            this._openingCrmUserReview = false;
        },

        openCreateCrmUserReview() {
            return this.fetchMatchingRoles()
                .catch(() => ({rolesIds: [], rolesNames: {}}))
                .then(roles => {
                const attributes = this.buildUserAttributes();

                if (roles.rolesIds && roles.rolesIds.length && (!attributes.rolesIds || !attributes.rolesIds.length)) {
                    attributes.rolesIds = roles.rolesIds;
                    attributes.rolesNames = Object.assign({}, attributes.rolesNames || {}, roles.rolesNames);
                }

                return ContactTypeSet.presentCreateCrmUserReview(this, attributes);
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
                        this.lastSaveCancelReason = 'cancel';

                        return Promise.reject('cancel');
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
