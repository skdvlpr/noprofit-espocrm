define('workflow-engine:views/modals/edit-assignment', [
    'views/modal',
    'model',
    'workflow-engine:helpers/field-list',
], function (Dep, Model, FieldListHelper) {

    /**
     * Edit one field assignment: target field + raw | field | formula value.
     */
    return Dep.extend({

        className: 'dialog dialog-record',
        backdrop: true,
        fitHeight: true,

        templateContent: `
<div class="record no-side-margin">
    <div class="cell form-group" data-name="field">
        <label class="control-label">
            {{translate 'TargetField' category='fields' scope='WorkflowDefinition'}}
        </label>
        <div class="field">
            <select class="form-control" data-name="field">{{{targetFieldOptionsHtml}}}</select>
        </div>
    </div>
    <div class="cell form-group" data-name="sourceType">
        <label class="control-label">
            {{translate 'ValueSource' category='fields' scope='WorkflowDefinition'}}
        </label>
        <div class="field">
            <select class="form-control" data-name="sourceType">
                <option value="raw"{{#if isRaw}} selected{{/if}}>
                    {{translate 'ValueSourceRaw' category='labels' scope='WorkflowDefinition'}}
                </option>
                <option value="field"{{#if isField}} selected{{/if}}>
                    {{translate 'ValueSourceField' category='labels' scope='WorkflowDefinition'}}
                </option>
                <option value="formula"{{#if isFormula}} selected{{/if}}>
                    {{translate 'ValueSourceFormula' category='labels' scope='WorkflowDefinition'}}
                </option>
            </select>
        </div>
    </div>
    <div class="cell form-group" data-name="rawValue"{{#unless isRaw}} hidden{{/unless}}>
        <label class="control-label">{{translate 'Value' category='fields' scope='WorkflowDefinition'}}</label>
        <div class="field" data-name="rawValueContainer"></div>
    </div>
    <div class="cell form-group" data-name="sourceField"{{#unless isField}} hidden{{/unless}}>
        <label class="control-label">
            {{translate 'SourceField' category='fields' scope='WorkflowDefinition'}}
        </label>
        <div class="field">
            <select class="form-control" data-name="sourceField">{{{sourceFieldOptionsHtml}}}</select>
        </div>
    </div>
    <div class="cell form-group" data-name="expression"{{#unless isFormula}} hidden{{/unless}}>
        <label class="control-label">
            {{translate 'Expression' category='fields' scope='WorkflowDefinition'}}
        </label>
        <div class="field" data-name="expressionContainer"></div>
    </div>
</div>
`,

        data: function () {
            const sourceType = this.state.sourceType || 'raw';

            return {
                isRaw: sourceType === 'raw',
                isField: sourceType === 'field',
                isFormula: sourceType === 'formula',
                targetFieldOptionsHtml: this.buildOptionsHtml(
                    this.targetFieldList,
                    this.targetFieldLabels,
                    this.state.field
                ),
                sourceFieldOptionsHtml: this.buildOptionsHtml(
                    this.sourceFieldList,
                    this.sourceFieldLabels,
                    this.state.sourceField
                ),
            };
        },

        setup: function () {
            Dep.prototype.setup.call(this);

            this.helper = new FieldListHelper(this);
            this.workflowEntityType = this.options.workflowEntityType || '';
            this.targetEntityType = this.options.targetEntityType || this.workflowEntityType;
            this.assignment = Espo.Utils.cloneDeep(this.options.assignment || {});

            this.state = {
                field: this.assignment.field || '',
                sourceType: this.normalizeSourceType(this.assignment.sourceType),
                value: this.assignment.value ?? '',
                sourceField: this.assignment.sourceField || '',
                expression: this.assignment.expression || '',
            };

            const targetOpts = this.helper.getTargetFieldOptions(this.targetEntityType);
            const sourceOpts = this.helper.getSourceFieldOptions(this.workflowEntityType);

            this.targetFieldList = targetOpts.list;
            this.targetFieldLabels = targetOpts.labels;
            this.sourceFieldList = sourceOpts.list;
            this.sourceFieldLabels = sourceOpts.labels;

            this.headerText = this.translate('EditAssignment', 'labels', 'WorkflowDefinition');
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

            this.expressionModel = new Model();
            this.expressionModel.name = 'WorkflowAssignmentExpression';
            this.expressionModel.set('expression', this.state.expression || '');
            this.expressionModel.setDefs({
                fields: {
                    expression: {
                        type: 'formula',
                        view: 'views/fields/formula',
                    },
                },
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            this.$el.find('select[data-name="sourceType"]').on('change', e => {
                this.captureDom();
                this.state.sourceType = e.currentTarget.value || 'raw';
                this.reRender();
            });

            this.$el.find('select[data-name="field"]').on('change', e => {
                this.state.field = e.currentTarget.value || '';
                this.renderRawValue();
            });

            this.renderRawValue();
            this.renderFormula();
        },

        captureDom: function () {
            this.state.field = this.$el.find('select[data-name="field"]').val() || this.state.field;
            this.state.sourceField = this.$el.find('select[data-name="sourceField"]').val() ||
                this.state.sourceField;

            if (this.state.sourceType === 'raw') {
                this.state.value = this.fetchRawValue();
            }
        },

        buildOptionsHtml: function (list, labels, selected) {
            const parts = ['<option value=""></option>'];

            (list || []).forEach(name => {
                const sel = name === selected ? ' selected' : '';
                const label = (labels && labels[name]) ? labels[name] : name;

                parts.push(
                    '<option value="' + this.escapeAttr(name) + '"' + sel + '>' +
                    this.escapeHtml(label) + '</option>'
                );
            });

            return parts.join('');
        },

        renderRawValue: function () {
            const $container = this.$el.find('[data-name="rawValueContainer"]');

            if (!$container.length || this.state.sourceType !== 'raw') {
                return;
            }

            const fieldType = this.helper.getFieldType(this.targetEntityType, this.state.field);
            const value = this.state.value;
            let html;

            if (fieldType === 'bool') {
                const checked = value ? ' checked' : '';

                html = '<div class="checkbox"><label><input type="checkbox" data-name="rawValue"' +
                    checked + '> ' + this.translate('Yes') + '</label></div>';
            }
            else if (fieldType === 'enum') {
                const options = this.helper.getEnumOptions(this.targetEntityType, this.state.field);
                const opts = ['<option value=""></option>'].concat(options.map(opt => {
                    const selected = String(value) === String(opt) ? ' selected' : '';

                    return '<option value="' + this.escapeAttr(opt) + '"' + selected + '>' +
                        this.escapeHtml(String(opt)) + '</option>';
                }));

                html = '<select class="form-control" data-name="rawValue">' + opts.join('') + '</select>';
            }
            else if (fieldType === 'text') {
                html = '<textarea class="form-control" data-name="rawValue" rows="4">' +
                    this.escapeHtml(value == null ? '' : String(value)) + '</textarea>';
            }
            else if (fieldType === 'int' || fieldType === 'float' || fieldType === 'currency') {
                html = '<input type="number" class="form-control" data-name="rawValue" value="' +
                    this.escapeAttr(value == null ? '' : value) + '">';
            }
            else if (fieldType === 'date') {
                html = '<input type="date" class="form-control" data-name="rawValue" value="' +
                    this.escapeAttr(value == null ? '' : value) + '">';
            }
            else {
                html = '<input type="text" class="form-control" data-name="rawValue" value="' +
                    this.escapeAttr(value == null ? '' : value) + '">';
            }

            $container.html(html);
        },

        renderFormula: function () {
            if (this.state.sourceType !== 'formula') {
                if (this.hasView('expressionField')) {
                    this.clearView('expressionField');
                }

                return;
            }

            this.expressionModel.set('expression', this.state.expression || '', {silent: true});

            this.createView('expressionField', 'views/fields/formula', {
                model: this.expressionModel,
                name: 'expression',
                mode: 'edit',
                selector: '[data-name="expressionContainer"]',
                targetEntityType: this.workflowEntityType,
                height: 200,
                smallFont: true,
            }, view => {
                if (this.isRendered()) {
                    view.render();
                }
            });
        },

        actionApply: function () {
            this.state.field = this.$el.find('select[data-name="field"]').val() || '';
            this.state.sourceType = this.$el.find('select[data-name="sourceType"]').val() || 'raw';
            this.state.sourceField = this.$el.find('select[data-name="sourceField"]').val() || '';

            if (!this.state.field) {
                Espo.Ui.warning(this.translate('selectTargetFieldFirst', 'messages', 'WorkflowDefinition'));

                return;
            }

            if (this.state.sourceType === 'field' && !this.state.sourceField) {
                Espo.Ui.warning(this.translate('selectSourceFieldFirst', 'messages', 'WorkflowDefinition'));

                return;
            }

            if (this.state.sourceType === 'raw') {
                this.state.value = this.fetchRawValue();
            }

            if (this.state.sourceType === 'formula') {
                const expressionView = this.getView('expressionField');

                if (expressionView && expressionView.fetch) {
                    const data = expressionView.fetch();

                    this.state.expression = data.expression || '';
                }
                else {
                    this.state.expression = this.expressionModel.get('expression') || '';
                }

                if (!String(this.state.expression).trim()) {
                    Espo.Ui.warning(this.translate('enterFormulaFirst', 'messages', 'WorkflowDefinition'));

                    return;
                }
            }

            this.trigger('apply', {
                field: this.state.field,
                sourceType: this.state.sourceType,
                value: this.state.sourceType === 'raw' ? this.state.value : null,
                sourceField: this.state.sourceType === 'field' ? this.state.sourceField : '',
                expression: this.state.sourceType === 'formula' ? this.state.expression : '',
            });

            this.close();
        },

        fetchRawValue: function () {
            const fieldType = this.helper.getFieldType(this.targetEntityType, this.state.field);
            const $input = this.$el.find('[data-name="rawValue"]');

            if (!$input.length) {
                return this.state.value;
            }

            if (fieldType === 'bool') {
                return $input.is(':checked');
            }

            if (fieldType === 'int') {
                const n = parseInt($input.val(), 10);

                return Number.isNaN(n) ? null : n;
            }

            if (fieldType === 'float' || fieldType === 'currency') {
                const n = parseFloat($input.val());

                return Number.isNaN(n) ? null : n;
            }

            return $input.val();
        },

        normalizeSourceType: function (value) {
            const v = (value || 'raw').toString();

            if (v === 'constant' || v === 'raw') {
                return 'raw';
            }

            if (v === 'expression') {
                return 'formula';
            }

            return v;
        },

        escapeHtml: function (value) {
            return $('<div>').text(value).html();
        },

        escapeAttr: function (value) {
            return this.escapeHtml(String(value)).replace(/"/g, '&quot;');
        },
    });
});
