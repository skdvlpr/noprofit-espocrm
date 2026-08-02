define('google-integration:views/fields/google-calendar-description-template', [
    'exports',
    'views/fields/text',
    'nonprofit-espocrm:lib/template-variable-inserter',
], function (_exports, _text, Inserter) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _text = _interopRequireDefault(_text);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    /**
     * Calendar template text with shared native Segnaposti-style {{field}} inserter.
     * (Replaces the exclusive Google side panel — see TemplateVariablesUI for future upgrade.)
     */
    class GoogleCalendarDescriptionTemplateField extends _text.default {
        setup() {
            if (this.model.getFieldType(this.name) === 'varchar') {
                this.editTemplate = 'fields/varchar/edit';
                this.detailTemplate = 'fields/varchar/detail';
                this.listTemplate = 'fields/varchar/list';
                this.searchTemplate = 'fields/varchar/search';
            }

            super.setup();

            if (this.model.entityType === 'CalendarTemplate') {
                this.listenTo(this.model, 'change:targetEntityType', () => {
                    this.scheduleVariablePickerRender();
                });
            }
        }

        afterRender() {
            super.afterRender();

            if (!this.isEditMode()) {
                return;
            }

            this.renderVariablePicker();

            if (this.model.entityType !== 'CalendarTemplate') {
                return;
            }

            const targetField = this.getTargetEntityTypeFieldView();

            if (targetField) {
                this.listenTo(targetField, 'after:render', () => this.renderVariablePicker());
            }

            setTimeout(() => this.renderVariablePicker(), 0);
        }

        scheduleVariablePickerRender() {
            if (!this.isEditMode()) {
                return;
            }

            if (this.isRendered()) {
                this.renderVariablePicker();

                return;
            }

            this.once('after:render', () => this.renderVariablePicker());
        }

        getTargetEntityTypeFieldView() {
            const recordView = this.recordHelper?.recordView;

            if (!recordView || typeof recordView.getFieldView !== 'function') {
                return null;
            }

            return recordView.getFieldView('targetEntityType');
        }

        readTargetEntityTypeFromField() {
            const targetField = this.getTargetEntityTypeFieldView();

            if (!targetField || !targetField.isRendered()) {
                return null;
            }

            const value = targetField.$el.find('select').val();

            return value || null;
        }

        getTemplateEntityType() {
            if (this.model.entityType === 'CalendarTemplate') {
                return this.model.get('targetEntityType')
                    || this.readTargetEntityTypeFromField()
                    || null;
            }

            return this.model.entityType;
        }

        renderVariablePicker() {
            this.$el.find('.nonprofit-template-variable-inserter').remove();
            this.$el.find('.google-calendar-template-variable-helper').remove();

            if (!this.isEditMode()) {
                return;
            }

            Inserter.render({
                $container: this.$el,
                entityType: this.getTemplateEntityType(),
                metadata: this.getMetadata(),
                language: this.getLanguage(),
                translate: (key, category, scope) => this.translate(key, category, scope),
                emptyHint: this.translate(
                    'googleCalendarSelectTargetEntityFirst',
                    'labels',
                    'CalendarDateSource'
                ),
                onInsert: token => {
                    Inserter.insertToken(this.$el, token, this.model, this.name);
                    this.trigger('change');
                },
            });
        }
    }

    _exports.default = GoogleCalendarDescriptionTemplateField;
});
