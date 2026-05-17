define('google-integration:handlers/google-calendar/save-to-google-handler', ['exports', 'bullbone'], function (_exports, Bull) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    class SaveToGoogleHandler {
        constructor(view) {
            this.view = view;
            this.model = view.model;
        }

        process() {
            this.listenTo(this.view, 'after:render', () => this.control());

            this.listenTo(this.model, 'change:saveToGoogleCalendar', () => {
                if (!this.model.get('saveToGoogleCalendar')) {
                    this.model.set({
                        googleCalendarReminderMode: 'none',
                        googleCalendarReminders: [],
                    });
                }

                this.control();
            });

            this.listenTo(this.model, 'change:googleCalendarReminderMode', () => this.control());
        }

        control() {
            const enabled = !!this.model.get('saveToGoogleCalendar');
            const customReminders = enabled && this.model.get('googleCalendarReminderMode') === 'custom';

            this.toggleField('googleCalendarReminderMode', enabled);
            this.toggleField('googleCalendarReminders', customReminders);
            this.toggleField('googleCalendarDescriptionTemplateOverride', enabled);
            this.toggleField('googleCalendarLocation', enabled);
            this.toggleField('googleCalendarVisibility', enabled);
            this.toggleField('googleCalendarTransparency', enabled);
            this.toggleField('googleCalendarColorId', enabled);
            this.toggleTemplateVariableHelper('googleCalendarDescriptionTemplateOverride', enabled);
            this.toggleNotice(enabled);
        }

        toggleField(field, visible) {
            if (visible) {
                this.view.showField(field);
                return;
            }

            this.view.hideField(field);
        }

        toggleNotice(visible) {
            this.removeNotice();

            if (!visible || !this.view.$el) {
                return;
            }

            const $target = this.view.$el
                .find('[data-name="saveToGoogleCalendar"]')
                .last();

            if (!$target.length) {
                return;
            }

            const text = this.view.translate(
                'googleCalendarReminderNotice',
                'labels',
                this.view.entityType
            );

            const $notice = $('<div>')
                .addClass('alert alert-warning google-calendar-reminder-notice margin-top')
                .text(text);

            const $container = $target.closest('.cell, .field');

            if ($container.length) {
                $container.after($notice);
                return;
            }

            $target.after($notice);
        }

        removeNotice() {
            if (!this.view.$el) {
                return;
            }

            this.view.$el.find('.google-calendar-reminder-notice').remove();
        }

        toggleTemplateVariableHelper(field, visible) {
            this.removeTemplateVariableHelper(field);

            if (!visible || !this.view.$el) {
                return;
            }

            const $field = this.view.$el.find(`.field[data-name="${field}"]`).last();

            if (!$field.length) {
                return;
            }

            const fieldList = this.getInsertableFieldList();

            if (!fieldList.length) {
                return;
            }

            const $select = $('<select>')
                .addClass('form-control input-sm')
                .attr('data-role', 'google-calendar-template-variable');

            fieldList.forEach(item => {
                $('<option>')
                    .attr('value', item.name)
                    .text(item.label)
                    .appendTo($select);
            });

            const $button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-default btn-sm')
                .text(this.view.translate('Insert'))
                .on('click', () => this.insertVariable(field, String($select.val() || '')));

            const $helper = $('<div>')
                .addClass('google-calendar-template-variable-helper input-group input-group-sm margin-top')
                .attr('data-for-field', field);

            $('<span>')
                .addClass('input-group-addon')
                .text(this.view.translate('googleCalendarTemplateVariables', 'labels', this.view.entityType))
                .appendTo($helper);

            $select.appendTo($helper);

            $('<span>')
                .addClass('input-group-btn')
                .append($button)
                .appendTo($helper);

            $field.append($helper);
        }

        removeTemplateVariableHelper(field) {
            if (!this.view.$el) {
                return;
            }

            this.view.$el
                .find(`.google-calendar-template-variable-helper[data-for-field="${field}"]`)
                .remove();
        }

        getInsertableFieldList() {
            const fields = this.view.getMetadata().get(`entityDefs.${this.view.entityType}.fields`) || {};

            return Object.keys(fields)
                .filter(name => !name.startsWith('googleCalendar') && !fields[name].utility)
                .map(name => ({
                    name,
                    label: this.view.translate(name, 'fields', this.view.entityType),
                }))
                .sort((a, b) => a.label.localeCompare(b.label));
        }

        insertVariable(field, name) {
            if (!name || !this.view.$el) {
                return;
            }

            const $input = this.view.$el
                .find(`.field[data-name="${field}"] textarea, .field[data-name="${field}"] input`)
                .first();

            if (!$input.length) {
                return;
            }

            const element = $input.get(0);
            const value = $input.val() || '';
            const variable = `{{${name}}}`;
            const start = element.selectionStart ?? String(value).length;
            const end = element.selectionEnd ?? start;
            const nextValue = String(value).slice(0, start) + variable + String(value).slice(end);

            $input.val(nextValue).trigger('change');

            if (typeof element.setSelectionRange === 'function') {
                const cursor = start + variable.length;
                element.setSelectionRange(cursor, cursor);
            }
        }
    }

    Object.assign(SaveToGoogleHandler.prototype, Bull.Events);

    _exports.default = SaveToGoogleHandler;
});
