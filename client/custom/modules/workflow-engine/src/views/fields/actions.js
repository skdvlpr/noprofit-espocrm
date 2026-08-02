define('workflow-engine:views/fields/actions', ['views/fields/base'], function (Dep) {

    /**
     * Ordered action list: Notify, Email, UpdateFields, CreateRecord.
     */
    return Dep.extend({

        editTemplateContent: `
<div class="workflow-engine-actions">
    <div class="small text-muted workflow-engine-actions-help">
        {{translate 'systemWriteWarning' category='messages' scope='WorkflowDefinition'}}
    </div>
    <div class="list-group workflow-engine-actions-list">
        {{#each itemDataList}}
        <div class="list-group-item">
            <div class="clearfix">
                <div class="pull-right">
                    <a role="button" tabindex="0" data-action="removeAction" data-index="{{index}}"
                       title="{{translate 'Remove'}}"><span class="fas fa-minus fa-sm"></span></a>
                </div>
                <div class="pull-left"><strong>{{label}}</strong></div>
            </div>
            <div class="small text-muted" style="margin-top: 6px;">{{summary}}</div>
            <div style="margin-top: 8px;">
                <a role="button" tabindex="0" data-action="editAction" data-index="{{index}}">
                    {{translate 'Edit'}}
                </a>
            </div>
        </div>
        {{/each}}
    </div>
    <div style="margin-top: 8px;">
        <a role="button" tabindex="0" data-action="showAddModal" title="{{translate 'Add'}}">
            <span class="fas fa-plus fa-sm"></span>
            <span style="margin-left: 4px;">
                {{translate 'AddAction' category='labels' scope='WorkflowDefinition'}}
            </span>
        </a>
    </div>
</div>
`,

        detailTemplateContent: `
{{#if itemDataList.length}}
<div class="list-group workflow-engine-actions-list">
    {{#each itemDataList}}
    <div class="list-group-item">
        <div><strong>{{label}}</strong></div>
        <div class="small text-muted" style="margin-top: 6px;">{{summary}}</div>
    </div>
    {{/each}}
</div>
{{else}}
<span class="text-muted">{{translate 'None'}}</span>
{{/if}}
`,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.addActionHandler('showAddModal', () => this.actionAddItem());
            this.addActionHandler('editAction', (e, target) => {
                this.editAction(parseInt(target.dataset.index, 10));
            });
            this.addActionHandler('removeAction', (e, target) => {
                this.removeAction(parseInt(target.dataset.index, 10));
            });

            this.actionConfigList = this.normalizeActionConfigList(this.model.get(this.name));
            this.availableActionList = [
                'UpdateFields',
                'CreateRecord',
                'CreateNotification',
                'SendEmail',
            ];

            // List→detail SPA: actions jsonArray often arrives on fetch after setup.
            this.listenTo(this.model, 'sync', () => this.reloadFromModelAndRender());
            this.listenTo(this.model, 'change:' + this.name, () => this.reloadFromModelAndRender());
        },

        reloadFromModelAndRender: async function () {
            this.actionConfigList = this.normalizeActionConfigList(this.model.get(this.name));

            if (this.isRendered()) {
                await this.reRender();
            }
        },

        data: function () {
            return {
                ...Dep.prototype.data.call(this),
                itemDataList: this.getItemDataList(),
            };
        },

        fetch: function () {
            const data = {};

            data[this.name] = this.actionConfigList.length ?
                Espo.Utils.cloneDeep(this.actionConfigList) :
                null;

            return data;
        },

        actionAddItem: function () {
            this.createView('dialog', 'views/modals/array-field-add', {
                options: this.availableActionList,
                translatedOptions: this.getTranslatedOptions(),
            }, view => {
                view.render();

                this.listenToOnce(view, 'add', value => {
                    view.close();
                    this.addAction(value);
                });
            });
        },

        addAction: function (type) {
            const config = this.createDefaultActionConfig(type);

            if (!config) {
                return;
            }

            const index = this.actionConfigList.length;

            this.actionConfigList.push(config);
            this.reRender();
            this.trigger('change');
            this.editAction(index, true);
        },

        removeAction: function (index) {
            if (!this.actionConfigList[index]) {
                return;
            }

            this.actionConfigList.splice(index, 1);
            this.reRender();
            this.trigger('change');
        },

        editAction: function (index, isNew) {
            const actionConfig = this.actionConfigList[index];

            if (!actionConfig) {
                return;
            }

            this.createView('dialog', 'workflow-engine:views/modals/edit-action', {
                actionConfig: Espo.Utils.cloneDeep(actionConfig),
                targetEntityType: this.model.get('targetEntityType') || '',
            }, view => {
                view.render();

                this.listenToOnce(view, 'apply', updated => {
                    this.actionConfigList[index] = updated;
                    this.model.set(this.name, Espo.Utils.cloneDeep(this.actionConfigList), {silent: true});
                    this.reRender();
                    this.trigger('change');
                });

                if (isNew) {
                    this.listenToOnce(view, 'close', () => {
                        const current = this.actionConfigList[index];

                        if (current && !this.hasMeaningfulPayload(current)) {
                            this.actionConfigList.splice(index, 1);
                            this.reRender();
                            this.trigger('change');
                        }
                    });
                }
            });
        },

        getItemDataList: function () {
            return this.actionConfigList.map((item, index) => ({
                index: index,
                label: this.translateActionLabel(item),
                summary: this.getActionSummary(item),
            }));
        },

        getTranslatedOptions: function () {
            const map = {};

            this.availableActionList.forEach(value => {
                map[value] = this.translate(value, 'options', 'WorkflowDefinition') || value;
            });

            return map;
        },

        translateActionLabel: function (item) {
            const type = item.type || '';

            return this.translate(type, 'options', 'WorkflowDefinition') || type;
        },

        getActionSummary: function (item) {
            const type = item.type || '';

            if (type === 'CreateNotification') {
                const to = item.to || 'assignedUser';
                const toLabel = this.translate(to, 'options', 'WorkflowDefinition') || to;
                const message = (item.message || '').trim();

                return message ? (toLabel + ' · ' + message) : toLabel;
            }

            if (type === 'SendEmail') {
                const to = item.to || 'assignedUser';
                let toLabel = this.translate(to, 'options', 'WorkflowDefinition') || to;

                if (to === 'assignedUser' || to === 'createdBy') {
                    const idx = String(item.emailAddressIndex || 1);
                    const idxLabel =
                        this.getLanguage().translateOption(idx, 'emailAddressIndex', 'WorkflowDefinition') ||
                        ('#' + idx);

                    toLabel = toLabel + ' · ' + idxLabel;
                }

                if (item.emailTemplateId) {
                    const templateName = (item.emailTemplateName || '').trim()
                        || this.translate('emailTemplate', 'fields', 'WorkflowDefinition');

                    return toLabel + ' · ' + templateName;
                }

                const subject = (item.subject || '').trim();

                return subject ? (toLabel + ' · ' + subject) : toLabel;
            }

            if (type === 'UpdateFields') {
                const count = Array.isArray(item.assignments) ? item.assignments.length : 0;

                return this.translate('Fields', 'labels', 'WorkflowDefinition') + ': ' + count;
            }

            if (type === 'CreateRecord') {
                const entityType = item.entityType || '—';
                const count = Array.isArray(item.assignments) ? item.assignments.length : 0;
                const scopeLabel = this.translate(entityType, 'scopeNames') || entityType;

                return scopeLabel + ' · ' +
                    this.translate('Fields', 'labels', 'WorkflowDefinition') + ': ' + count;
            }

            return '';
        },

        createDefaultActionConfig: function (type) {
            if (type === 'CreateNotification') {
                return {type: 'CreateNotification', to: 'assignedUser', message: ''};
            }

            if (type === 'SendEmail') {
                return {
                    type: 'SendEmail',
                    to: 'assignedUser',
                    emailAddressIndex: 1,
                    emailTemplateId: null,
                    emailTemplateName: null,
                    subject: '',
                    body: '',
                    isHtml: true,
                    additionalTo: '',
                    cc: '',
                    bcc: '',
                };
            }

            if (type === 'UpdateFields') {
                return {type: 'UpdateFields', assignments: []};
            }

            if (type === 'CreateRecord') {
                return {type: 'CreateRecord', entityType: '', assignments: []};
            }

            return null;
        },

        normalizeActionConfigList: function (value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value
                .map(item => {
                    if (!item || typeof item !== 'object') {
                        return null;
                    }

                    const type = item.type || '';

                    if (type === 'CreateNotification') {
                        return {
                            type: 'CreateNotification',
                            to: item.to || 'assignedUser',
                            message: item.message || '',
                            userId: item.userId || null,
                        };
                    }

                    if (type === 'SendEmail') {
                        return {
                            type: 'SendEmail',
                            to: item.to || 'assignedUser',
                            emailAddressIndex: parseInt(item.emailAddressIndex || 1, 10) || 1,
                            emailTemplateId: item.emailTemplateId || null,
                            emailTemplateName: item.emailTemplateName || null,
                            subject: item.subject || '',
                            body: item.body || '',
                            isHtml: item.isHtml !== false,
                            additionalTo: item.additionalTo || '',
                            cc: item.cc || '',
                            bcc: item.bcc || '',
                            email: item.email || null,
                            userId: item.userId || null,
                        };
                    }

                    if (type === 'UpdateFields') {
                        return {
                            type: 'UpdateFields',
                            assignments: Array.isArray(item.assignments) ? item.assignments : [],
                        };
                    }

                    if (type === 'CreateRecord') {
                        return {
                            type: 'CreateRecord',
                            entityType: item.entityType || '',
                            assignments: Array.isArray(item.assignments) ? item.assignments : [],
                        };
                    }

                    return null;
                })
                .filter(item => item !== null);
        },

        hasMeaningfulPayload: function (item) {
            if (!item) {
                return false;
            }

            if (item.type === 'CreateNotification') {
                return !!(item.message && String(item.message).trim());
            }

            if (item.type === 'SendEmail') {
                return !!(
                    item.emailTemplateId ||
                    (item.subject && String(item.subject).trim()) ||
                    (item.body && String(item.body).trim())
                );
            }

            if (item.type === 'UpdateFields') {
                return Array.isArray(item.assignments) && item.assignments.length > 0;
            }

            if (item.type === 'CreateRecord') {
                return !!(item.entityType && String(item.entityType).trim());
            }

            return false;
        },
    });
});
