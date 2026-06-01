define('google-integration:views/admin/integrations/template-modal', [
    'views/modal',
    'google-integration:lib/google-calendar-variable-panel',
    'google-integration:lib/google-calendar-template-variables',
], function (Dep, VariablePanel, TemplateVariables) {
    'use strict';

    return Dep.extend({
        className: 'dialog dialog-record',

        templateContent: `
            <div class="panel panel-default">
                <div class="panel-body">
                    <label class="control-label">{{templateFieldLabel}}</label>
                    <div class="field" data-name="templateField"></div>
                    <div data-role="variable-helper"></div>
                </div>
            </div>
        `,

        setup() {
            this.headerText = this.options.entityLabel || this.options.entityType;
            this.templateFieldLabel = this.options.templateFieldLabel || '';
            this.fieldName = this.options.fieldName;
            this.entityType = this.options.entityType;
            this.templateValue = this.options.value || '';

            this.buttonList = [
                {
                    name: 'apply',
                    label: 'Apply',
                    style: 'primary',
                    onClick: () => this.apply(),
                },
                {
                    name: 'cancel',
                    label: 'Cancel',
                    onClick: () => this.close(),
                },
            ];
        },

        data() {
            return {
                templateFieldLabel: this.templateFieldLabel,
            };
        },

        afterRender() {
            const $textarea = $('<textarea>')
                .addClass('form-control')
                .attr('rows', 8)
                .val(this.templateValue)
                .appendTo(this.$el.find('[data-name="templateField"]'));

            this.$textarea = $textarea;

            this.renderVariableHelper($textarea);
        },

        renderVariableHelper($textarea) {
            const fieldList = this.getInsertableFieldList(this.entityType);

            if (!fieldList.length) {
                return;
            }

            const $btn = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm margin-top-sm')
                .text(this.translate('googleCalendarTemplateVariables', 'labels', 'Global'))
                .on('click', () => {
                    VariablePanel.open({
                        stateKey: `integration-template-${this.entityType}`,
                        anchorEl: $textarea,
                        fieldList: fieldList,
                        ownerView: this,
                        onSelect: name => this.insertVariable($textarea, name),
                        translate: (key, category, scope) => this.translate(key, category, scope || 'Global'),
                        title: this.translate('googleCalendarTemplateVariables', 'labels', 'Global'),
                    });
                });

            this.$el.find('[data-role="variable-helper"]').empty().append($btn);
        },

        getInsertableFieldList(entityType) {
            return TemplateVariables.buildInsertableFieldList({
                metadata: this.getMetadata(),
                entityType,
                translate: (key, category, scope) => this.translate(key, category, scope || 'Global'),
                currentGroupLabel: this.translate('googleCalendarCurrentRecordFields', 'labels', 'Global'),
                relatedGroupLabel: this.translate('googleCalendarRelatedRecordFields', 'labels', 'Global'),
            });
        },

        insertVariable($textarea, name) {
            const element = $textarea.get(0);
            const value = $textarea.val() || '';
            const variable = `{{${name}}}`;
            const start = element.selectionStart ?? String(value).length;
            const end = element.selectionEnd ?? start;
            const nextValue = String(value).slice(0, start) + variable + String(value).slice(end);

            $textarea.val(nextValue);

            if (typeof element.setSelectionRange === 'function') {
                const cursor = start + variable.length;
                element.setSelectionRange(cursor, cursor);
            }

            $textarea.trigger('focus');
        },

        apply() {
            this.trigger('apply', {
                fieldName: this.fieldName,
                value: this.$textarea.val() || '',
            });

            this.close();
        },
    });
});
