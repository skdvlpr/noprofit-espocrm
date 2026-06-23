define('google-integration:views/calendar-date-export-panel', ['view'], function (Dep) {
    'use strict';

    return Dep.extend({
        templateContent: `
            <div class="google-calendar-date-export-panel panel panel-default">
                <div class="panel-body">
                    <label class="checkbox-inline">
                        <input type="checkbox" data-role="saveToGoogleCalendar"{{#if enabled}} checked{{/if}}>
                        {{saveLabel}}
                    </label>
                    {{#if sourceList.length}}
                        <div class="margin-top-sm">
                            <label class="control-label small">{{dateSourceLabel}}</label>
                            <select class="form-control input-sm" data-role="dateSource"{{#unless enabled}} disabled{{/unless}}>
                                {{#each sourceList}}
                                    <option value="{{sourceDateType}}">{{label}}</option>
                                {{/each}}
                            </select>
                        </div>
                    {{/if}}
                    <div class="margin-top-sm">
                        <label class="control-label small">{{templateLabel}}</label>
                        <select class="form-control input-sm" data-role="calendarTemplate"{{#unless enabled}} disabled{{/unless}}>
                            <option value=""></option>
                            {{#each templateList}}
                                <option value="{{id}}">{{name}}</option>
                            {{/each}}
                        </select>
                    </div>
                </div>
            </div>
        `,

        data() {
            return {
                enabled: !!this.model.get('saveToGoogleCalendar'),
                saveLabel: this.translate('saveToGoogleCalendar', 'fields', this.model.entityType),
                dateSourceLabel: this.translate('CalendarDateSource', 'scopeNamesPlural') || 'Date fields',
                templateLabel: this.translate('googleCalendarTemplate', 'fields', this.model.entityType),
                sourceList: this.options.sourceList || [],
                templateList: this.options.templateList || [],
            };
        },

        setup() {
            this.globalIntegrationEnabled = false;

            return Dep.prototype.setup.call(this).then(() => {
                const integrations = this.getConfig().get('integrations') || {};

                if (!integrations.GoogleCalendarDrive) {
                    return;
                }

                return Espo.Ajax.getRequest('GoogleIntegration/integration-status')
                    .then(data => {
                        this.globalIntegrationEnabled = !!data.enabled;
                    })
                    .catch(() => {
                        this.globalIntegrationEnabled = false;
                    });
            });
        },

        afterRender() {
            if (!this.globalIntegrationEnabled) {
                this.$el.addClass('hidden');

                return;
            }

            this.$el.on('change', '[data-role="saveToGoogleCalendar"]', e => {
                this.model.set('saveToGoogleCalendar', $(e.currentTarget).is(':checked'), {ui: true});
                this.reRender();
            });

            this.$el.on('change', '[data-role="calendarTemplate"]', e => {
                this.model.set('googleCalendarTemplateId', $(e.currentTarget).val() || null, {ui: true});
            });
        },
    });
});
