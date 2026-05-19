define('google-integration:views/calendar/google-calendar-manager', ['view'], function (Dep) {
    'use strict';

    return Dep.extend({
        templateContent: `
            <div class="google-calendar-manager panel panel-default">
                <div class="panel-heading">
                    <strong>{{translate 'Google Calendar' category='scopeNames'}}</strong>
                </div>
                <div class="panel-body">
                    <select class="form-control input-sm margin-bottom-sm" data-role="googleCalendarFilter" multiple>
                        {{#each calendarList}}
                            <option value="{{id}}" selected>{{summary}}</option>
                        {{/each}}
                    </select>
                    <div class="google-calendar-events" data-role="googleEvents"></div>
                </div>
            </div>
        `,

        data() {
            return {
                calendarList: this.calendarList || [],
            };
        },

        setup() {
            this.calendarList = [];
            this.eventList = [];
        },

        afterRender() {
            this.loadCalendars();
        },

        loadCalendars() {
            Espo.Ajax.getRequest('GoogleIntegration/calendar/google-calendars')
                .then(data => {
                    this.calendarList = data.list || [];
                    this.reRender();
                });
        },

        loadEvents(from, to) {
            const calendarIds = this.$el.find('[data-role="googleCalendarFilter"]').val() || ['primary'];

            return Promise.all(calendarIds.map(calendarId => Espo.Ajax.getRequest('GoogleIntegration/calendar/google-events', {
                calendarId,
                timeMin: from,
                timeMax: to,
            }))).then(resultList => {
                this.eventList = resultList.flatMap(result => result.items || []);
                this.renderEvents();
            });
        },

        createEvent(calendarId, event) {
            return Espo.Ajax.postRequest('GoogleIntegration/calendar/google-events', {calendarId, event});
        },

        updateEvent(calendarId, eventId, event) {
            return Espo.Ajax.putRequest(
                `GoogleIntegration/calendar/google-events/${encodeURIComponent(calendarId)}/${encodeURIComponent(eventId)}`,
                {event}
            );
        },

        deleteEvent(calendarId, eventId) {
            return Espo.Ajax.deleteRequest(
                `GoogleIntegration/calendar/google-events/${encodeURIComponent(calendarId)}/${encodeURIComponent(eventId)}`
            );
        },

        renderEvents() {
            const $container = this.$el.find('[data-role="googleEvents"]').empty();

            if (!this.eventList.length) {
                $('<span>').addClass('none-value').text(this.translate('None')).appendTo($container);
                return;
            }

            this.eventList.forEach(event => {
                $('<div>')
                    .addClass('small')
                    .text(`${event.summary || this.translate('None')} ${event.start?.dateTime || event.start?.date || ''}`)
                    .appendTo($container);
            });
        },
    });
});
