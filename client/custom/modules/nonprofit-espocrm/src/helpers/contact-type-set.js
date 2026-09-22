define('nonprofit-espocrm:helpers/contact-type-set', [], function () {

    /**
     * Contact / Lead contactType list helpers and record-view picker.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/fields.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/administration/roles-management.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/custom-views.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/view.md
     */
    const OPTIONS = [
        'HelpSeeker',
        'Colleague',
        'Volunteer',
        'Employee',
        'MemberContact',
        'AssociationRepresentative',
        'Other',
    ];

    const TYPE_TO_ROLE = {
        Volunteer: 'Volunteer',
        Employee: 'Employee',
        MemberContact: 'Member',
    };

    const list = function (value) {
        if (Array.isArray(value)) {
            return value.filter(item => typeof item === 'string' && item !== '');
        }

        if (typeof value === 'string' && value !== '') {
            return [value];
        }

        return [];
    };

    const unique = function (types) {
        return [...new Set(list(types))];
    };

    const isLegal = function (types) {
        const items = unique(types);

        if (items.length === 0) {
            return true;
        }

        if (items.length === 1) {
            return OPTIONS.indexOf(items[0]) !== -1;
        }

        if (items.length !== 2) {
            return false;
        }

        const set = new Set(items);
        const volMember = set.has('Volunteer') && set.has('MemberContact');
        const empMember = set.has('Employee') && set.has('MemberContact');

        return volMember || empMember;
    };

    const wantsCrmUser = function (types) {
        const items = unique(types);

        return items.indexOf('Volunteer') !== -1 ||
            items.indexOf('Employee') !== -1 ||
            items.indexOf('MemberContact') !== -1;
    };

    const createUserDefaultOn = function (types) {
        const items = unique(types);

        return items.indexOf('Volunteer') !== -1 || items.indexOf('MemberContact') !== -1;
    };

    const roleNames = function (types) {
        const out = [];

        unique(types).forEach(type => {
            if (TYPE_TO_ROLE[type] && out.indexOf(TYPE_TO_ROLE[type]) === -1) {
                out.push(TYPE_TO_ROLE[type]);
            }
        });

        return out;
    };

    const entityTypeOf = function (view) {
        return view.model.name || view.entityType || 'Contact';
    };

    const restrictOptionsOnView = function (view) {
        const fieldView = view.getFieldView('contactType');

        if (!fieldView) {
            return;
        }

        const metaOptions = view.getMetadata().get([
            'entityDefs', entityTypeOf(view), 'fields', 'contactType', 'options',
        ]) || [];
        const current = list(view.model.get('contactType'));
        let options = metaOptions.slice();
        const prevParams = (fieldView.params && fieldView.params.options) ?
            fieldView.params.options.slice() : [];

        if (!view.getUser().isAdmin()) {
            options = options.filter(value => {
                if (value === 'Volunteer' || value === 'Employee') {
                    return current.indexOf(value) !== -1;
                }

                return true;
            });
        }

        if (current.indexOf('Volunteer') !== -1) {
            options = options.filter(value => value !== 'Employee');
        }

        if (current.indexOf('Employee') !== -1) {
            options = options.filter(value => value !== 'Volunteer');
        }

        const optionListChanged = JSON.stringify(prevParams) !== JSON.stringify(options);

        fieldView.selected = current.slice();

        if (!optionListChanged) {
            return;
        }

        if (typeof fieldView.setOptionList === 'function') {
            fieldView.setOptionList(options, true);
        }
        else if (fieldView.params) {
            fieldView.params.options = options;
        }
    };

    const enforceLegalOnView = function (view) {
        const current = list(view.model.get('contactType'));

        if (isLegal(current)) {
            return;
        }

        view.model.set('contactType', view.model.previous('contactType'), {silent: true});
        Espo.Ui.error(view.translate('illegalContactTypeCombination', 'messages', 'Contact'));
        restrictOptionsOnView(view);
    };

    const attachToRecordView = function (view, afterChange) {
        view.listenTo(view.model, 'change:contactType', () => {
            enforceLegalOnView(view);
            restrictOptionsOnView(view);

            if (typeof afterChange === 'function') {
                afterChange.call(view);
            }
        });

        view.once('after:render', () => {
            restrictOptionsOnView(view);

            if (typeof afterChange === 'function') {
                afterChange.call(view);
            }
        });
    };

    const dropCreateCrmUserReview = function (parentView) {
        if (parentView._droppingCrmUserReview) {
            if (parentView.getView('createCrmUser')) {
                parentView.clearView('createCrmUser');
            }

            return;
        }

        parentView._droppingCrmUserReview = true;

        try {
            const existing = parentView.getView('createCrmUser');

            if (existing && typeof existing.close === 'function') {
                try {
                    existing.close();
                }
                catch (e) {}
            }

            if (parentView.getView('createCrmUser')) {
                parentView.clearView('createCrmUser');
            }
        }
        finally {
            parentView._droppingCrmUserReview = false;
        }
    };

    /**
     * Nested review: Espo onDialogClose remove() can leave the child key.
     * record/edit actionSave treats a resolved save() as success and exits.
     *
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/modal.md
     * Cite: https://github.com/espocrm/documentation/blob/master/docs/development/view.md
     */
    const presentCreateCrmUserReview = function (parentView, attributes) {
        parentView._crmUserReviewSeq = (parentView._crmUserReviewSeq || 0) + 1;

        const seq = parentView._crmUserReviewSeq;

        dropCreateCrmUserReview(parentView);

        parentView._crmUserReviewConfirmed = false;
        parentView._openingCrmUserReview = true;

        return new Promise(resolve => {
            let settled = false;

            const finish = function (result) {
                if (settled || seq !== parentView._crmUserReviewSeq) {
                    return;
                }

                settled = true;
                parentView._openingCrmUserReview = false;

                if (result !== 'confirmed') {
                    parentView._crmUserDraft = null;
                    parentView._crmUserReviewConfirmed = false;
                    dropCreateCrmUserReview(parentView);
                }

                resolve(result);
            };

            parentView.createView('createCrmUser', 'nonprofit-espocrm:views/modals/create-crm-user', {
                scope: 'User',
                attributes: attributes,
                fullFormDisabled: true,
                headerText: parentView.translate('Create User', 'labels', 'User'),
                onConfirm: function (confirmed) {
                    parentView._crmUserDraft = confirmed;
                    parentView._crmUserReviewConfirmed = true;
                    finish('confirmed');
                },
                onCancel: function () {
                    finish('cancelled');
                },
            }, function (view) {
                if (seq !== parentView._crmUserReviewSeq) {
                    dropCreateCrmUserReview(parentView);

                    return;
                }

                parentView.listenToOnce(view, 'remove', function () {
                    if (!parentView._crmUserReviewConfirmed) {
                        finish('cancelled');
                    }
                });

                view.render();
            });
        });
    };

    return {
        OPTIONS: OPTIONS,
        list: list,
        unique: unique,
        isLegal: isLegal,
        wantsCrmUser: wantsCrmUser,
        createUserDefaultOn: createUserDefaultOn,
        roleNames: roleNames,
        attachToRecordView: attachToRecordView,
        restrictOptionsOnView: restrictOptionsOnView,
        enforceLegalOnView: enforceLegalOnView,
        dropCreateCrmUserReview: dropCreateCrmUserReview,
        presentCreateCrmUserReview: presentCreateCrmUserReview,
    };
});
