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
                this.control();
            });
        }

        control() {
            const enabled = !!this.model.get('saveToGoogleCalendar');
            this.toggleField('googleCalendarDateSourceList', enabled);
            this.toggleField('googleCalendarEventSettings', enabled);
            this.toggleNotice(enabled);
        }

        toggleField(field, visible) {
            if (!this.hasField(field)) {
                return;
            }

            if (visible) {
                this.view.showField(field);
                return;
            }

            this.view.hideField(field);
        }

        hasField(field) {
            return !!this.view.getMetadata().get(`entityDefs.${this.model.entityType}.fields.${field}`);
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
    }

    Object.assign(SaveToGoogleHandler.prototype, Bull.Events);

    _exports.default = SaveToGoogleHandler;
});
