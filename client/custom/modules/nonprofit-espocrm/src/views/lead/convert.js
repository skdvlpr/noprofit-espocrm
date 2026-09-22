define('nonprofit-espocrm:views/lead/convert', [
    'crm:views/lead/convert',
    'nonprofit-espocrm:helpers/contact-type-set',
], function (ConvertView, ContactTypeSet) {

    ConvertView = ConvertView.default || ConvertView;

    /**
     * Native convert plus optional 004.2 CRM-user review. Cancel persists nothing.
     * MUST NOT fork ConvertService.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/user-guide/sales-management.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/view.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
     */
    return class extends ConvertView {

        setup() {
            super.setup();
            this._crmUserDraft = null;
            this._crmUserReviewConfirmed = false;
            this._openingCrmUserReview = false;
        }

        build() {
            const scopeList = this.scopeList = [];

            (this.getMetadata().get('entityDefs.Lead.convertEntityList') || []).forEach(scope => {
                if (scope === 'Account' && this.getConfig().get('b2cMode')) {
                    return;
                }

                if (this.getMetadata().get(['scopes', scope, 'disabled'])) {
                    return;
                }

                if (this.getAcl().check(scope, 'create')) {
                    scopeList.push(scope);
                }
            });

            let i = 0;

            if (scopeList.length === 0) {
                this.wait(false);

                return;
            }

            Espo.Ui.notifyWait();

            Espo.Ajax.postRequest('Lead/action/getConvertAttributes', {
                id: this.model.id,
            }).then(data => {
                scopeList.forEach(scope => {
                    this.getModelFactory().create(scope, model => {
                        model.populateDefaults();
                        model.set(data[scope] || {}, {silent: true});

                        if (scope === 'Contact') {
                            this.applyContactConvertDefaults(model);
                        }

                        const convertEntityViewName = this.getMetadata().get([
                            'clientDefs', scope, 'recordViews', 'edit',
                        ]) || 'views/record/edit';

                        this.createView(scope, convertEntityViewName, {
                            model: model,
                            fullSelector: '#main .edit-container-' + Espo.Utils.toDom(scope),
                            buttonsPosition: false,
                            buttonsDisabled: true,
                            layoutName: 'detailConvert',
                            exit: () => {},
                        }, view => {
                            if (scope === 'Contact' && view && typeof view.processDynamicLogic === 'function') {
                                view.processDynamicLogic();
                                ContactTypeSet.restrictOptionsOnView(view);
                            }

                            i++;

                            if (i === scopeList.length) {
                                this.wait(false);
                                Espo.Ui.notify(false);
                            }
                        });
                    });
                });
            });
        }

        applyContactConvertDefaults(model) {
            let types = ContactTypeSet.list(model.get('contactType'));

            if (types.length === 0) {
                types = ContactTypeSet.list(this.model.get('contactType'));
            }

            if (types.length === 0) {
                types = ['Other'];
            }

            model.set({
                contactType: types.slice(),
                createCrmUser: ContactTypeSet.createUserDefaultOn(types),
            });
        }

        convert() {
            const scopeList = [];

            this.scopeList.forEach(scope => {
                const el = this.$el.find(`input[data-scope="${scope}"]`).get(0);

                if (el && el.checked) {
                    scopeList.push(scope);
                }
            });

            const contactView = this.getView('Contact');
            const createUser = !!(contactView && contactView.model && contactView.model.get('createCrmUser'));

            if (createUser && scopeList.indexOf('Contact') === -1) {
                Espo.Ui.error(this.translate('selectAtLeastOneRecord', 'messages'));

                return;
            }

            if (scopeList.length === 0) {
                Espo.Ui.error(this.translate('selectAtLeastOneRecord', 'messages'));

                return;
            }

            this.getRouter().confirmLeaveOut = false;

            let notValid = false;

            scopeList.forEach(scope => {
                const editView = this.getView(scope);

                editView.model.set(editView.fetch());
                notValid = editView.validate() || notValid;
            });

            this.scopeList.forEach(scope => {
                const editView = this.getView(scope);

                if (!editView) {
                    return;
                }

                editView.setConfirmLeaveOut(false);
            });

            if (notValid) {
                Espo.Ui.error(this.translate('Not valid'));

                return;
            }

            const data = {
                id: this.model.id,
                records: {},
            };

            scopeList.forEach(scope => {
                data.records[scope] = this.getView(scope).model.attributes;
            });

            if (createUser && (!contactView.model.get('emailAddress'))) {
                Espo.Ui.error(this.translate('createCrmUserNeedsEmail', 'messages', 'Contact'));

                return;
            }

            if (createUser && !this._crmUserReviewConfirmed) {
                this.openCreateCrmUserReview(contactView).then(result => {
                    if (result !== 'confirmed') {
                        return;
                    }

                    this.processConvert(data, true);
                });

                return;
            }

            this.processConvert(data, createUser && this._crmUserReviewConfirmed);
        }

        openCreateCrmUserReview(contactView) {
            const types = ContactTypeSet.list(contactView.model.get('contactType'));
            const wanted = ContactTypeSet.roleNames(types);

            return Espo.Ajax.getRequest('Role', {
                maxSize: 200,
                select: 'id,name',
            }).catch(() => ({list: []})).then(response => {
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

                const identity = typeof contactView.copiedIdentityFromContact === 'function' ?
                    contactView.copiedIdentityFromContact() :
                    {
                        firstName: contactView.model.get('firstName'),
                        lastName: contactView.model.get('lastName'),
                        emailAddress: contactView.model.get('emailAddress'),
                        phoneNumber: contactView.model.get('phoneNumber'),
                    };

                const email = identity.emailAddress || '';
                let userName = null;

                if (email.includes('@')) {
                    userName = email.split('@')[0].replace(/[^a-zA-Z0-9._-]/g, '') || null;
                }

                const attributes = Object.assign({}, identity, {
                    type: 'regular',
                    isActive: true,
                    userName: userName,
                    sendAccessInfo: !!email,
                    rolesIds: rolesIds,
                    rolesNames: rolesNames,
                });

                return ContactTypeSet.presentCreateCrmUserReview(this, attributes);
            });
        }

        processConvert(data, createUser) {
            data = Espo.Utils.cloneDeep(data);

            if (data.records && data.records.Contact) {
                const contact = data.records.Contact;

                delete contact.createCrmUser;

                const types = ContactTypeSet.list(contact.contactType);

                if (
                    types.indexOf('Employee') === -1 ||
                    contact.contractType === '' ||
                    contact.contractType === null
                ) {
                    delete contact.contractType;
                }
            }

            this.$el.find('[data-action="convert"]').addClass('disabled');
            Espo.Ui.notifyWait();

            Espo.Ajax.postRequest('Lead/action/convert', data).then(() => {
                this.getRouter().confirmLeaveOut = false;

                if (!createUser) {
                    this.getRouter().navigate('#Lead/view/' + this.model.id, {trigger: true});
                    Espo.Ui.notify(this.translate('Converted', 'labels', 'Lead'));

                    return;
                }

                return this.model.fetch().then(() => this.createUserAfterConvert());
            }).then(() => {
                if (!createUser) {
                    return;
                }

                this.getRouter().navigate('#Lead/view/' + this.model.id, {trigger: true});
                Espo.Ui.notify(this.translate('Converted', 'labels', 'Lead'));
            }).catch(xhr => {
                Espo.Ui.notify(false);
                this.$el.find('[data-action="convert"]').removeClass('disabled');

                if (xhr.status !== 409) {
                    return;
                }

                if (xhr.getResponseHeader('X-Status-Reason') !== 'duplicate') {
                    return;
                }

                let response = null;

                try {
                    response = JSON.parse(xhr.responseText);
                }
                catch (e) {
                    console.error('Could not parse response header.');

                    return;
                }

                xhr.errorIsHandled = true;

                this.createView('duplicate', 'views/modals/duplicate', {
                    duplicates: response,
                }, view => {
                    view.render();
                    this.listenToOnce(view, 'save', () => {
                        data.skipDuplicateCheck = true;
                        this.processConvert(data, createUser);
                    });
                });
            });
        }

        createUserAfterConvert() {
            const contactId = this.model.get('createdContactId');

            if (!contactId || !this._crmUserDraft) {
                return Promise.resolve();
            }

            const payload = Object.assign(
                {},
                Espo.Utils.cloneDeep(this._crmUserDraft),
                {
                    sourceContactId: contactId,
                    type: 'regular',
                }
            );

            delete payload.password;
            delete payload.passwordConfirm;
            delete payload.passwordPreview;
            delete payload.passwordInfo;
            delete payload.generatePassword;

            ['id', 'createdAt', 'modifiedAt', 'deleted'].forEach(key => {
                delete payload[key];
            });

            Object.keys(payload).forEach(key => {
                if (payload[key] === null || payload[key] === undefined) {
                    delete payload[key];
                }
            });

            return Espo.Ajax.postRequest('User', payload)
                .then(() => {
                    this._crmUserDraft = null;
                    this._crmUserReviewConfirmed = false;
                })
                .catch(xhr => {
                    let error = this.translate('Error occurred', 'messages');

                    if (xhr && xhr.responseJSON && xhr.responseJSON.message) {
                        error = xhr.responseJSON.message;
                    }
                    else if (xhr && xhr.responseText) {
                        error = String(xhr.responseText).slice(0, 240);
                    }

                    Espo.Ui.error(
                        this.translate('createCrmUserFailed', 'messages', 'Contact')
                            .replace('{error}', error)
                    );
                });
        }
    };
});
