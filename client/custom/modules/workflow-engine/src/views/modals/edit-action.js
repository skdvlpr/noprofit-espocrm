define('workflow-engine:views/modals/edit-action', ['views/modal', 'model'], function (Dep, Model) {

    return Dep.extend({

        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: true,

        templateContent: `
<div class="record no-side-margin">{{{record}}}</div>
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

            this.model = new Model();
            this.model.name = 'WorkflowActionEdit';
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
                };
            }

            if (type === 'SendEmail') {
                return {
                    to: cfg.to || 'assignedUser',
                    subject: cfg.subject || '',
                    body: cfg.body || '',
                    isHtml: cfg.isHtml !== false,
                    email: cfg.email || '',
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
            return {
                fields: {
                    to: {
                        type: 'enum',
                        options: ['assignedUser', 'createdBy'],
                        translation: 'WorkflowDefinition.options',
                    },
                    message: {type: 'text'},
                    subject: {type: 'varchar'},
                    body: {type: 'text'},
                    isHtml: {type: 'bool'},
                    email: {type: 'varchar'},
                    createEntityType: {
                        type: 'varchar',
                        view: 'views/fields/entity-type',
                    },
                    assignments: {
                        type: 'jsonArray',
                        view: 'workflow-engine:views/fields/field-assignments',
                    },
                },
            };
        },

        buildDetailLayout: function () {
            const type = this.actionConfig.type;
            let rows = [];

            if (type === 'CreateNotification') {
                rows = [
                    [{name: 'to'}],
                    [{name: 'message', fullWidth: true}],
                ];
            }
            else if (type === 'SendEmail') {
                rows = [
                    [{name: 'to'}],
                    [{name: 'subject', fullWidth: true}],
                    [{name: 'body', fullWidth: true}],
                    [{name: 'isHtml'}, {name: 'email'}],
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

            if (this.actionConfig.type !== 'CreateRecord') {
                return;
            }

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
                updated.subject = this.model.get('subject') || '';
                updated.body = this.model.get('body') || '';
                updated.isHtml = !!this.model.get('isHtml');

                const email = (this.model.get('email') || '').trim();

                if (email) {
                    updated.email = email;
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
