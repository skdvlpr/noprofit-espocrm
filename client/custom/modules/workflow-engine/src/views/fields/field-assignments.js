define('workflow-engine:views/fields/field-assignments', [
    'views/fields/base',
    'workflow-engine:helpers/field-list',
], function (Dep, FieldListHelper) {

    /**
     * Ordered list of field assignments for UpdateFields / CreateRecord.
     */
    return Dep.extend({

        editTemplateContent: `
<div class="workflow-engine-field-assignments">
    <div class="list-group">
        {{#each itemDataList}}
        <div class="list-group-item">
            <div class="clearfix">
                <div class="pull-right">
                    <a role="button" tabindex="0" data-action="removeItem" data-index="{{index}}"
                       title="{{translate 'Remove'}}"><span class="fas fa-minus fa-sm"></span></a>
                </div>
                <div class="pull-left"><strong>{{fieldLabel}}</strong></div>
            </div>
            <div class="small text-muted" style="margin-top: 4px;">{{summary}}</div>
            <div style="margin-top: 6px;">
                <a role="button" tabindex="0" data-action="editItem" data-index="{{index}}">
                    {{translate 'Edit'}}
                </a>
            </div>
        </div>
        {{/each}}
    </div>
    <div style="margin-top: 8px;">
        <a role="button" tabindex="0" data-action="addItem">
            <span class="fas fa-plus fa-sm"></span>
            <span style="margin-left: 4px;">
                {{translate 'AddFieldAssignment' category='labels' scope='WorkflowDefinition'}}
            </span>
        </a>
    </div>
</div>
`,

        detailTemplateContent: `
{{#if itemDataList.length}}
<div class="list-group">
    {{#each itemDataList}}
    <div class="list-group-item">
        <div><strong>{{fieldLabel}}</strong></div>
        <div class="small text-muted">{{summary}}</div>
    </div>
    {{/each}}
</div>
{{else}}
<span class="text-muted">{{translate 'None'}}</span>
{{/if}}
`,

        setup: function () {
            Dep.prototype.setup.call(this);

            this.helper = new FieldListHelper(this);
            this.workflowEntityType = this.options.workflowEntityType ||
                this.params.workflowEntityType || '';
            this.targetEntityType = this.options.targetEntityType ||
                this.params.targetEntityType ||
                this.workflowEntityType;

            this.itemList = this.normalize(this.model.get(this.name));

            this.addActionHandler('addItem', () => this.openEditor(null));
            this.addActionHandler('editItem', (e, target) => {
                this.openEditor(parseInt(target.dataset.index, 10));
            });
            this.addActionHandler('removeItem', (e, target) => {
                this.removeItem(parseInt(target.dataset.index, 10));
            });

            this.listenTo(this.model, 'change:targetEntityType change:createEntityType', () => {
                // Parent modal may update create entity type.
                if (this.model.has('createEntityType')) {
                    this.targetEntityType = this.model.get('createEntityType') || this.targetEntityType;
                }
            });
        },

        data: function () {
            return {
                ...Dep.prototype.data.call(this),
                itemDataList: this.getItemDataList(),
            };
        },

        fetch: function () {
            const data = {};

            data[this.name] = this.itemList.length ? Espo.Utils.cloneDeep(this.itemList) : [];

            return data;
        },

        openEditor: function (index) {
            const isNew = index === null || index === undefined;
            const assignment = isNew ? {} : Espo.Utils.cloneDeep(this.itemList[index] || {});

            if (!this.targetEntityType) {
                Espo.Ui.warning(
                    this.translate('selectTargetEntityTypeFirst', 'messages', 'WorkflowDefinition')
                );

                return;
            }

            this.createView('assignmentModal', 'workflow-engine:views/modals/edit-assignment', {
                assignment: assignment,
                workflowEntityType: this.workflowEntityType,
                targetEntityType: this.targetEntityType,
            }, view => {
                view.render();

                this.listenToOnce(view, 'apply', updated => {
                    if (isNew) {
                        this.itemList.push(updated);
                    }
                    else {
                        this.itemList[index] = updated;
                    }

                    this.model.set(this.name, Espo.Utils.cloneDeep(this.itemList), {silent: true});
                    this.reRender();
                    this.trigger('change');
                });
            });
        },

        removeItem: function (index) {
            if (!this.itemList[index]) {
                return;
            }

            this.itemList.splice(index, 1);
            this.model.set(this.name, Espo.Utils.cloneDeep(this.itemList), {silent: true});
            this.reRender();
            this.trigger('change');
        },

        getItemDataList: function () {
            const labels = this.helper.getTargetFieldOptions(this.targetEntityType).labels;

            return this.itemList.map((item, index) => ({
                index: index,
                fieldLabel: labels[item.field] || item.field || '—',
                summary: this.summarize(item),
            }));
        },

        summarize: function (item) {
            const sourceType = item.sourceType || 'raw';

            if (sourceType === 'field') {
                return this.translate('ValueSourceField', 'labels', 'WorkflowDefinition') +
                    ': ' + (item.sourceField || '');
            }

            if (sourceType === 'formula' || sourceType === 'expression') {
                const expr = (item.expression || '').trim();

                return this.translate('ValueSourceFormula', 'labels', 'WorkflowDefinition') +
                    ': ' + (expr.length > 60 ? expr.slice(0, 57) + '…' : expr);
            }

            const value = item.value;
            const text = value === null || value === undefined ? '' : String(value);

            return this.translate('ValueSourceRaw', 'labels', 'WorkflowDefinition') +
                ': ' + (text.length > 60 ? text.slice(0, 57) + '…' : text);
        },

        normalize: function (value) {
            if (!Array.isArray(value)) {
                return [];
            }

            return value
                .filter(item => item && typeof item === 'object' && item.field)
                .map(item => ({
                    field: item.field,
                    sourceType: item.sourceType === 'constant' ? 'raw' :
                        (item.sourceType === 'expression' ? 'formula' : (item.sourceType || 'raw')),
                    value: item.value ?? null,
                    sourceField: item.sourceField || '',
                    expression: item.expression || '',
                }));
        },
    });
});
