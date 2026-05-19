define('google-integration:handlers/calendar-date-source/record-setup', ['exports', 'bullbone'], function (_exports, Bull) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    class CalendarDateSourceRecordSetupHandler {
        constructor(view) {
            this.view = view;
            this.model = view.model;
        }

        process() {
            this.controlRecordSetup();
        }

        controlRecordSetup() {
            this.listenTo(this.model, 'change:dateField', () => {
                this.syncSourceDateTypeFromDateField();
            });

            this.listenTo(this.model, 'change:targetEntityType', () => {
                this.syncSourceDateTypeFromDateField();
                this.model.set('defaultTemplateId', null, {ui: true});
                this.model.set('defaultTemplateName', null, {ui: true});
            });
        }

        syncSourceDateTypeFromDateField() {
            const dateField = this.model.get('dateField');

            if (!dateField) {
                return;
            }

            let sourceDateType = 'main';

            if (dateField !== 'dateStart' && dateField !== 'dateEnd') {
                sourceDateType = dateField;
            }

            this.model.set('sourceDateType', sourceDateType, {ui: true});

            if (!this.model.get('label')) {
                const entityType = this.model.get('targetEntityType');
                const translated = entityType
                    ? this.view.getLanguage().translate(dateField, 'fields', entityType)
                    : '';
                const fieldLabel = translated && translated !== dateField
                    ? translated
                    : dateField
                        .replace(/([a-z0-9])([A-Z])/g, '$1 $2')
                        .replace(/^./, letter => letter.toUpperCase());

                this.model.set('label', fieldLabel, {ui: true});
            }
        }

    }

    Object.assign(CalendarDateSourceRecordSetupHandler.prototype, Bull.Events);

    _exports.default = CalendarDateSourceRecordSetupHandler;
});
