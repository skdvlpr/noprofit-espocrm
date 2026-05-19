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
                .text(this.translateLabel('googleCalendarTemplateVariables'))
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
                    onSelect: name => this.insertVariable(name),
                    translate: (key, category) => this.translateLabel(key, category),
                    title: this.translateLabel('googleCalendarTemplateVariables'),
                });
            });

            this.$el.append($helper);
        }

        translateLabel(key, category) {
            const entityType = this.getTemplateEntityType() || this.model.entityType;

            if (category === 'labels') {
                return this.translate(key, 'labels', entityType);
            }

            return this.translate(key, 'labels', this.model.entityType);
        }

        getInsertableFieldList(entityType) {
            const fields = this.getMetadata().get(`entityDefs.${entityType}.fields`) || {};
            const currentGroupLabel = this.translate('googleCalendarCurrentRecordFields', 'labels', entityType);
            const relatedGroupLabel = this.translate('googleCalendarRelatedRecordFields', 'labels', entityType);

            const currentFields = Object.keys(fields)
                .filter(name => this.isInsertableField(fields[name], name))
                .map(name => ({
                    name,
                    label: this.getFieldLabel(entityType, name),
                    group: 'current',
                    groupLabel: currentGroupLabel,
                }));

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

                Object.keys(relatedFields)
                    .filter(name => this.isInsertableField(relatedFields[name], name))
                    .map(name => ({
                        name: `${linkName}.${name}`,
                        label: `(${linkLabel}) ${this.getFieldLabel(relatedEntityType, name)}`,
                        group: `related-${linkName}`,
                        groupLabel,
                    }))
                    .sort((a, b) => a.label.localeCompare(b.label))
                    .forEach(item => list.push(item));
            });

            return list;
        }

        getFieldLabel(entityType, fieldName) {
            const translated = this.translate(fieldName, 'fields', entityType);

            if (translated && translated !== fieldName) {
                return translated;
            }

            return fieldName
                .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
                .replace(/^./, letter => letter.toUpperCase());
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
