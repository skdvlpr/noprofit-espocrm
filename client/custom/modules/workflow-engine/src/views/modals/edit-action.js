define('workflow-engine:views/modals/edit-action', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: true,

        templateContent: `
<div class="record no-side-margin">{{{record}}}</div>
<div class="workflow-engine-system-email-warning small text-warning"
     style="display:none;margin:8px 12px 0;"></div>
`,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.actionConfig = Espo.Utils.cloneDeep(this.options.actionConfig || {});
            this.workflowEntityType = this.options.targetEntityType || '';
            this.headerText = this.translateActionLabel(this.actionConfig.type);

            this.buttonList = [
                {
                    name: 'apply',
                    label: 'Apply',
                    style: 'primary',
                    onClick: () => this.actionApply(),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: () => this.actionClose(),
                },
            ];

            if (this.actionConfig.type === 'SendEmail') {
                this.buttonList.splice(1, 0, {
                    name: 'openEmailTemplate',
                    label: this.translate('EditEmailTemplate', 'labels', 'WorkflowDefinition'),
                    onClick: () => this.actionOpenEmailTemplate(),
                });
            }

            this.model = new Model();
            // Labels from WorkflowDefinition i18n; entityType EmailTemplate so wysiwyg
            // image upload sends relatedType/field accepted by Attachment API.
            this.model.name = 'WorkflowDefinition';
            if (this.actionConfig.type === 'SendEmail') {
                this.model.entityType = 'EmailTemplate';
            }
            else {
                this.model.entityType = 'WorkflowDefinition';
            }
            this.model.set(this.getInitialAttributes());
            this.model.setDefs(this.getModelDefs());

            this.createView('record', 'views/record/edit-for-modal', {
                model: this.model,
                selector: '.record',
                detailLayout: this.buildDetailLayout(),
                sideDisabled: true,
                bottomDisabled: true,
                isWide: true,
            });
        },

        getInitialAttributes: function () {
            const cfg = this.actionConfig;
            const type = cfg.type;

            if (type === 'CreateNotification') {
                return {
                    to: cfg.to || 'assignedUser',
                    message: cfg.message || '',
                    targetEntityType: this.workflowEntityType,
                };
            }

            if (type === 'SendEmail') {
                return {
                    to: cfg.to || 'assignedUser',
                    emailAddressIndex: String(cfg.emailAddressIndex || '1'),
                    emailTemplateId: cfg.emailTemplateId || null,
                    emailTemplateName: cfg.emailTemplateName || null,
                    subject: cfg.subject || '',
                    body: cfg.body || '',
                    isHtml: cfg.isHtml !== false,
                    additionalTo: cfg.additionalTo || '',
                    cc: cfg.cc || '',
                    bcc: cfg.bcc || '',
                    targetEntityType: this.workflowEntityType,
                };
            }

            if (type === 'UpdateFields') {
                return {
                    assignments: Array.isArray(cfg.assignments) ? cfg.assignments : [],
                };
            }

            if (type === 'CreateRecord') {
                return {
                    createEntityType: cfg.entityType || '',
                    assignments: Array.isArray(cfg.assignments) ? cfg.assignments : [],
                };
            }

            return {};
        },

        getModelDefs: function () {
            const toOptions = ['assignedUser', 'createdBy'];

            if (this.workflowEntityType) {
                toOptions.push('entityEmail');
            }

            return {
                fields: {
                    to: {
                        type: 'enum',
                        options: toOptions,
                        translation: 'WorkflowDefinition.options',
                    },
                    emailAddressIndex: {
                        type: 'enum',
                        options: ['1', '2', '3'],
                    },
                    message: {
                        type: 'text',
                        view: 'nonprofit-espocrm:views/fields/template-text',
                    },
                    emailTemplate: {
                        type: 'link',
                        entity: 'EmailTemplate',
                        view: 'views/fields/link',
                    },
                    subject: {
                        type: 'varchar',
                        view: 'nonprofit-espocrm:views/fields/template-text',
                    },
                    body: {
                        type: 'wysiwyg',
                        attachmentField: 'attachments',
                    },
                    attachments: {
                        type: 'attachmentMultiple',
                    },
                    isHtml: {type: 'bool'},
                    additionalTo: {
                        type: 'varchar',
                        view: 'views/email/fields/email-address-varchar',
                        tooltip: true,
                    },
                    cc: {
                        type: 'varchar',
                        view: 'views/email/fields/email-address-varchar',
                    },
                    bcc: {
                        type: 'varchar',
                        view: 'views/email/fields/email-address-varchar',
                    },
                    createEntityType: {
                        type: 'varchar',
                        view: 'views/fields/entity-type',
                    },
                    assignments: {
                        type: 'jsonArray',
                        view: 'workflow-engine:views/fields/field-assignments',
                    },
                },
                links: {
                    emailTemplate: {
                        type: 'belongsTo',
                        entity: 'EmailTemplate',
                    },
                },
            };
        },

        buildDetailLayout: function () {
            const type = this.actionConfig.type;
            let rows = [];
            const templateOpts = {
                templateEntityType: this.workflowEntityType,
            };

            if (type === 'CreateNotification') {
                rows = [
                    [{name: 'to'}],
                    [{name: 'message', fullWidth: true, options: templateOpts}],
                ];
            }
            else if (type === 'SendEmail') {
                rows = [
                    [{name: 'to'}, {name: 'emailAddressIndex'}],
                    [{name: 'emailTemplate', fullWidth: true}],
                    [{name: 'subject', fullWidth: true, options: templateOpts}],
                    [{name: 'body', fullWidth: true}],
                    // Hidden: required by wysiwyg image/file upload (attachmentField).
                    [{name: 'attachments', fullWidth: true}],
                    [{name: 'isHtml'}, false],
                    [{name: 'additionalTo', fullWidth: true}],
                    [{name: 'cc'}, {name: 'bcc'}],
                ];
            }
            else if (type === 'UpdateFields') {
                rows = [
                    [{
                        name: 'assignments',
                        fullWidth: true,
                        options: {
                            workflowEntityType: this.workflowEntityType,
                            targetEntityType: this.workflowEntityType,
                        },
                    }],
                ];
            }
            else if (type === 'CreateRecord') {
                rows = [
                    [{name: 'createEntityType'}],
                    [{
                        name: 'assignments',
                        fullWidth: true,
                        options: {
                            workflowEntityType: this.workflowEntityType,
                            targetEntityType: this.model.get('createEntityType') || '',
                        },
                    }],
                ];
            }

            return [{rows: rows}];
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            if (this.actionConfig.type === 'CreateRecord') {
                this.listenTo(this.model, 'change:createEntityType', () => {
                    const recordView = this.getView('record');
                    const fieldView = recordView && recordView.getFieldView ?
                        recordView.getFieldView('assignments') :
                        null;

                    if (fieldView) {
                        fieldView.targetEntityType = this.model.get('createEntityType') || '';
                        fieldView.itemList = [];
                        fieldView.model.set('assignments', [], {silent: true});
                        fieldView.reRender();
                    }
                });
            }

            if (this.actionConfig.type === 'SendEmail') {
                this.renderSystemEmailWarning();
                this.hideAttachmentsField();
                this.listenTo(this.model, 'change:emailTemplateId', () => {
                    this.loadEmailTemplateIntoEditor();
                });

                // If template linked but body empty (legacy), hydrate editor once.
                if (this.model.get('emailTemplateId') && !(this.model.get('body') || '').trim()) {
                    this.loadEmailTemplateIntoEditor();
                }

                this.listenTo(this.model, 'change:to', () => this.syncEmailIndexVisibility());
                this.syncEmailIndexVisibility();
            }
        },

        hideAttachmentsField: function () {
            const recordView = this.getView('record');
            const fieldView = recordView && recordView.getFieldView ?
                recordView.getFieldView('attachments') :
                null;

            if (fieldView && fieldView.$el) {
                fieldView.$el.closest('.cell').addClass('hidden');
            }
        },

        syncEmailIndexVisibility: function () {
            const recordView = this.getView('record');
            const fieldView = recordView && recordView.getFieldView ?
                recordView.getFieldView('emailAddressIndex') :
                null;

            if (!fieldView || !fieldView.$el) {
                return;
            }

            const to = this.model.get('to');
            const show = to === 'assignedUser' || to === 'createdBy';

            fieldView.$el.closest('.cell').toggle(show);
        },

        renderSystemEmailWarning: function () {
            const from = this.getConfig().get('outboundEmailFromAddress');
            const $el = this.$el.find('.workflow-engine-system-email-warning');

            if (!from) {
                $el.text(
                    this.translate('systemEmailNotConfigured', 'messages', 'WorkflowDefinition')
                ).show();
            }
            else {
                $el.hide();
            }
        },

        loadEmailTemplateIntoEditor: async function () {
            const id = this.model.get('emailTemplateId');

            if (!id) {
                return;
            }

            try {
                Espo.Ui.notify('...');
                const response = await Espo.Ajax.getRequest('EmailTemplate/' + id);
                Espo.Ui.notify(false);

                this.model.set({
                    subject: response.subject || '',
                    body: response.body || '',
                    isHtml: response.isHtml !== false,
                    emailTemplateName: response.name || this.model.get('emailTemplateName'),
                });

                const recordView = this.getView('record');

                if (recordView) {
                    ['subject', 'body', 'isHtml'].forEach(name => {
                        const fv = recordView.getFieldView(name);

                        if (fv && fv.isRendered()) {
                            fv.reRender();
                        }
                    });
                }
            }
            catch (e) {
                Espo.Ui.notify(false);
                Espo.Ui.error(
                    this.translate('emailTemplateLoadFailed', 'messages', 'WorkflowDefinition')
                );
            }
        },

        actionOpenEmailTemplate: function () {
            const id = this.model.get('emailTemplateId');

            if (!id) {
                Espo.Ui.warning(
                    this.translate('selectEmailTemplateFirst', 'messages', 'WorkflowDefinition')
                );

                return;
            }

            // Keep modal open content; open template in new hash tab-like navigation.
            window.open('#EmailTemplate/edit/' + id, '_blank');
        },

        actionApply: function () {
            const recordView = this.getView('record');

            if (recordView && recordView.fetch) {
                this.model.set(recordView.fetch());
            }

            const type = this.actionConfig.type;
            let updated = {type: type};

            if (type === 'CreateNotification') {
                updated.to = this.model.get('to') || 'assignedUser';
                updated.message = this.model.get('message') || '';
            }
            else if (type === 'SendEmail') {
                updated.to = this.model.get('to') || 'assignedUser';
                updated.emailAddressIndex = parseInt(this.model.get('emailAddressIndex') || '1', 10) || 1;
                updated.emailTemplateId = this.model.get('emailTemplateId') || null;
                updated.emailTemplateName = this.model.get('emailTemplateName') || null;
                updated.subject = this.model.get('subject') || '';
                updated.body = this.model.get('body') || '';
                updated.isHtml = !!this.model.get('isHtml');
                updated.additionalTo = (this.model.get('additionalTo') || '').trim();
                updated.cc = (this.model.get('cc') || '').trim();
                updated.bcc = (this.model.get('bcc') || '').trim();

                if (
                    !updated.emailTemplateId &&
                    !updated.subject &&
                    !updated.body &&
                    !updated.additionalTo
                ) {
                    Espo.Ui.warning(
                        this.translate('selectEmailTemplateOrBody', 'messages', 'WorkflowDefinition')
                    );

                    return;
                }

                if (!this.getConfig().get('outboundEmailFromAddress')) {
                    Espo.Ui.warning(
                        this.translate('systemEmailNotConfigured', 'messages', 'WorkflowDefinition')
                    );
                }
            }
            else if (type === 'UpdateFields') {
                updated.assignments = this.model.get('assignments') || [];

                if (!updated.assignments.length) {
                    Espo.Ui.warning(
                        this.translate('addAtLeastOneAssignment', 'messages', 'WorkflowDefinition')
                    );

                    return;
                }
            }
            else if (type === 'CreateRecord') {
                updated.entityType = this.model.get('createEntityType') || '';
                updated.assignments = this.model.get('assignments') || [];

                if (!updated.entityType) {
                    Espo.Ui.warning(
                        this.translate('selectTargetEntityTypeFirst', 'messages', 'WorkflowDefinition')
                    );

                    return;
                }
            }

            this.trigger('apply', updated);
            this.close();
        },

        translateActionLabel: function (type) {
            return this.translate(type, 'options', 'WorkflowDefinition') || type;
        },
    });
});
