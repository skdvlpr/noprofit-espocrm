define('google-integration:views/fields/google-calendar-description-template', [
    'exports',
    'views/fields/text',
    'google-integration:lib/google-calendar-variable-panel',
], function (_exports, _text, VariablePanel) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _text = _interopRequireDefault(_text);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class GoogleCalendarDescriptionTemplateField extends _text.default {
        setup() {
            super.setup();

            if (this.model.entityType === 'CalendarTemplate') {
                this.listenTo(this.model, 'change:targetEntityType', () => {
                    if (this.isRendered() && this.mode === this.MODE_EDIT) {
                        this.renderVariablePicker();
                    }
                });
            }
        }

        afterRender() {
            super.afterRender();

            if (this.mode !== 'edit') {
                return;
            }

            this.renderVariablePicker();
        }

        getTemplateEntityType() {
            if (this.model.entityType === 'CalendarTemplate') {
                return this.model.get('targetEntityType') || null;
            }

            return this.model.entityType;
        }

        renderVariablePicker() {
            this.$el.find('.google-calendar-template-variable-helper').remove();

            const templateEntityType = this.getTemplateEntityType();

            if (!templateEntityType) {
                return;
            }

            const $helper = $('<div>')
                .addClass('google-calendar-template-variable-helper')
                .css({marginTop: '8px'});

            const $toggle = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm')
                .css({borderRadius: '999px', paddingLeft: '14px', paddingRight: '14px'})
                .text(this.translateLabel('googleCalendarTemplateVariables', 'labels', 'Global'))
                .appendTo($helper);

            $toggle.on('click', () => {
                const $input = this.$el.find('textarea, input').first();
                const currentTemplateEntityType = this.getTemplateEntityType();

                if (!currentTemplateEntityType) {
                    return;
                }

                const fieldList = this.getInsertableFieldList(currentTemplateEntityType);

                if (!fieldList.length) {
                    return;
                }

                VariablePanel.open({
                    stateKey: `${this.model.entityType}:${this.name}:${currentTemplateEntityType}`,
                    anchorEl: $input.length ? $input : this.$el,
                    fieldList,
                    ownerView: this,
                    onSelect: name => this.insertVariable(name),
                    translate: (key, category, scope) => this.translateLabel(key, category, scope),
                    title: this.translateLabel('googleCalendarTemplateVariables', 'labels', 'Global'),
                });
            });

            this.$el.append($helper);
        }

        translateLabel(key, category = 'labels', scope = null) {
            const entityType = scope || this.getTemplateEntityType() || this.model.entityType;
            return this.translate(key, category, entityType);
        }

        getInsertableFieldList(entityType) {
            const fields = this.getMetadata().get(`entityDefs.${entityType}.fields`) || {};
            const currentGroupLabel = this.translate('googleCalendarCurrentRecordFields', 'labels', entityType);
            const relatedGroupLabel = this.translate('googleCalendarRelatedRecordFields', 'labels', entityType);

            const currentFields = Object.keys(fields)
                .filter(name => this.isInsertableField(fields[name], name))
                .map(name => {
                    const label = this.getFieldLabel(entityType, name);

                    if (!label) {
                        return null;
                    }

                    return {
                        name,
                        label,
                        group: 'current',
                        groupLabel: currentGroupLabel,
                    };
                })
                .filter(Boolean);

            return [
                ...currentFields.sort((a, b) => a.label.localeCompare(b.label)),
                ...this.getRelatedFieldList(entityType, relatedGroupLabel),
            ];
        }

        getRelatedFieldList(entityType, groupLabel) {
            const fields = this.getMetadata().get(`entityDefs.${entityType}.fields`) || {};
            const links = this.getMetadata().get(`entityDefs.${entityType}.links`) || {};
            const list = [];

            Object.keys(fields).forEach(linkName => {
                const field = fields[linkName] || {};

                if (!['link', 'linkMultiple'].includes(field.type) || !this.hasActualRelatedRecord(linkName, field.type)) {
                    return;
                }

                const relatedEntityType = links[linkName]?.entity;

                if (!relatedEntityType) {
                    return;
                }

                const relatedFields = this.getMetadata().get(`entityDefs.${relatedEntityType}.fields`) || {};
                const linkLabel = this.translate(linkName, 'fields', entityType);

                if (!linkLabel || linkLabel === linkName) {
                    return;
                }

                Object.keys(relatedFields)
                    .filter(name => this.isInsertableField(relatedFields[name], name))
                    .map(name => {
                        const label = this.getFieldLabel(relatedEntityType, name);

                        if (!label) {
                            return null;
                        }

                        return {
                            name: `${linkName}.${name}`,
                            label: `(${linkLabel}) ${label}`,
                            group: `related-${linkName}`,
                            groupLabel,
                        };
                    })
                    .filter(Boolean)
                    .sort((a, b) => a.label.localeCompare(b.label))
                    .forEach(item => list.push(item));
            });

            return list;
        }

        getFieldLabel(entityType, fieldName) {
            const translated = this.translate(fieldName, 'fields', entityType);
            return translated && translated !== fieldName ? translated : '';
        }

        hasActualRelatedRecord(linkName, type) {
            if (this.model.entityType === 'CalendarTemplate') {
                return true;
            }

            if (type === 'linkMultiple') {
                const ids = this.model.get(`${linkName}Ids`);
                return Array.isArray(ids) && ids.length > 0;
            }

            return !!this.model.get(`${linkName}Id`);
        }

        isInsertableField(field, name) {
            if (!field || field.utility || name.startsWith('googleCalendar')) {
                return false;
            }

            return !['link', 'linkMultiple', 'linkParent', 'file', 'image'].includes(field.type);
        }

        insertVariable(name) {
            if (!name) {
                return;
            }

            const $input = this.$el.find('textarea, input').first();

            if (!$input.length) {
                return;
            }

            const element = $input.get(0);
            const value = String($input.val() || '');
            const variable = `{{${name}}}`;
            const start = element.selectionStart ?? value.length;
            const end = element.selectionEnd ?? start;
            const nextValue = value.slice(0, start) + variable + value.slice(end);

            $input.val(nextValue).trigger('input').trigger('change');
            this.model.set(this.name, nextValue, {ui: true});

            if (typeof element.setSelectionRange === 'function') {
                const cursor = start + variable.length;
                element.setSelectionRange(cursor, cursor);
            }

            $input.trigger('focus');
        }
    }

    _exports.default = GoogleCalendarDescriptionTemplateField;
});
