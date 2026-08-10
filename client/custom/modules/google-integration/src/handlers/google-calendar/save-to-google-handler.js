define('google-integration:handlers/google-calendar/save-to-google-handler', ['exports', 'bullbone'], function (_exports, Bull) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    const GOOGLE_FIELD_NAMES = [
        'saveToGoogleCalendar',
        'googleCalendarDateSourceList',
        'googleCalendarEventSettings',
        'googleCalendarId',
        'googleCalendarShareUsers',
        'googleCalendarShareTeams',
    ];

    class SaveToGoogleHandler {
        constructor(view) {
            this.view = view;
            this.model = view.model;
            this.routingSources = [];
            this.routingHintsLoaded = false;
            this.globalIntegrationEnabled = null;
            this.integrationStatusLoaded = false;
            this.canShareToOthers = false;
        }

        process() {
            this.listenTo(this.view, 'after:render', () => this.control());

            this.listenTo(this.model, 'change:saveToGoogleCalendar', () => {
                this.control();
            });

            this.listenTo(this.model, 'change:googleCalendarDateSourceList', () => {
                this.control();
            });

            this.loadIntegrationStatus();
        }

        isConfigIntegrationFlagOn() {
            const integrations = this.view.getConfig().get('integrations') || {};

            return !!integrations.GoogleCalendarDrive;
        }

        loadIntegrationStatus() {
            if (!this.isConfigIntegrationFlagOn()) {
                this.globalIntegrationEnabled = false;
                this.canShareToOthers = false;
                this.integrationStatusLoaded = true;
                this.control();

                return;
            }

            Espo.Ajax.getRequest('GoogleIntegration/integration-status')
                .then(data => {
                    this.globalIntegrationEnabled = !!data.enabled;
                    this.canShareToOthers = !!data.canShareToOthers;
                    this.integrationStatusLoaded = true;
                    this.control();

                    if (this.globalIntegrationEnabled) {
                        this.loadRoutingHints();
                    }
                })
                .catch(() => {
                    this.globalIntegrationEnabled = false;
                    this.canShareToOthers = false;
                    this.integrationStatusLoaded = true;
                    this.control();
                });
        }

        loadRoutingHints() {
            const entityType = this.model.entityType;

            if (!entityType) {
                return;
            }

            Espo.Ajax.getRequest(`GoogleIntegration/calendar/date-source-options/${entityType}`)
                .then(data => {
                    this.routingSources = Array.isArray(data.sources) ? data.sources : [];
                    this.routingHintsLoaded = true;
                    this.control();
                })
                .catch(() => {
                    this.routingSources = [];
                    this.routingHintsLoaded = true;
                    this.control();
                });
        }

        getSelectedSourceDateTypes() {
            const selected = this.model.get('googleCalendarDateSourceList');

            if (!Array.isArray(selected) || selected.length === 0) {
                // Match EventPusher::getSelectedDateSourceTypes — explicit list required on save.
                return [];
            }

            return selected.map(item => String(item));
        }

        getSelectedRoutingSources() {
            const selectedTypes = this.getSelectedSourceDateTypes();

            return this.routingSources.filter(source => {
                const type = String(source.sourceDateType || 'main');

                return selectedTypes.includes(type);
            });
        }

        isUserPickEnabledForSelection() {
            return this.getSelectedRoutingSources().some(
                source => source.calendarRoutingMode === 'user_pick'
            );
        }

        hideGoogleCalendarUi() {
            this.removeNotice();
            this.removeRoutingHint();
            this.removeShareHelp();

            GOOGLE_FIELD_NAMES.forEach(field => this.toggleField(field, false));

            if (typeof this.view.hidePanel === 'function') {
                this.view.hidePanel('GoogleCalendar');
            }
        }

        control() {
            if (!this.integrationStatusLoaded) {
                return;
            }

            if (!this.globalIntegrationEnabled) {
                this.hideGoogleCalendarUi();

                return;
            }

            const enabled = !!this.model.get('saveToGoogleCalendar');
            // Always offer a calendar picker (incl. "create new calendar") once export is on —
            // not only for explicit user_pick routing sources. See product spec (B).
            const showCalendarPicker = enabled;
            const showShare = enabled && this.canShareToOthers;

            this.toggleField('saveToGoogleCalendar', true);
            this.toggleField('googleCalendarDateSourceList', enabled);
            this.toggleField('googleCalendarEventSettings', enabled);
            this.toggleField('googleCalendarId', showCalendarPicker);
            this.toggleField('googleCalendarShareUsers', showShare);
            this.toggleField('googleCalendarShareTeams', showShare);
            this.toggleNotice(enabled);
            this.toggleRoutingHint(enabled);
            this.toggleShareHelp(showShare);

            if (showCalendarPicker) {
                this.refreshGoogleCalendarField();
            }
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

        refreshGoogleCalendarField() {
            const fieldView = this.view.getFieldView('googleCalendarId');

            if (!fieldView || typeof fieldView.ensureCalendarsLoaded !== 'function') {
                return;
            }

            fieldView.ensureCalendarsLoaded();
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
                .append(
                    $('<span>')
                        .addClass('google-calendar-reminder-notice-text')
                        .text(text)
                );

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

        toggleRoutingHint(visible) {
            this.removeRoutingHint();

            if (!visible || !this.view.$el || !this.routingHintsLoaded) {
                return;
            }

            const lines = this.buildRoutingHintLines();

            if (lines.length === 0) {
                return;
            }

            const $target = this.view.$el
                .find('.google-calendar-reminder-notice')
                .last();

            if (!$target.length) {
                return;
            }

            const title = this.view.translate(
                'googleCalendarRoutingHintTitle',
                'labels',
                this.view.entityType
            );

            const $hint = $('<div>')
                .addClass('alert alert-info google-calendar-routing-hint margin-top')
                .append($('<strong>').addClass('google-calendar-routing-hint-title').text(title));

            lines.forEach(line => {
                $hint.append($('<div>').addClass('google-calendar-routing-hint-line').text(line));
            });

            $target.after($hint);
        }

        buildRoutingHintLines() {
            const sources = this.getSelectedRoutingSources();

            if (sources.length === 0) {
                return [];
            }

            return sources.map(source => this.buildRoutingHintLine(source));
        }

        buildRoutingHintLine(source) {
            const label = String(source.label || source.sourceDateType || 'main');
            const mode = String(source.calendarRoutingMode || 'primary');
            const prefix = `${label}: `;

            if (mode === 'user_pick') {
                return prefix + this.view.translate(
                    'googleCalendarRoutingHintUserPick',
                    'labels',
                    this.view.entityType
                );
            }

            if (mode === 'auto_dedicated') {
                const calendarName = String(
                    source.resolvedDedicatedCalendarName
                    || source.dedicatedCalendarName
                    || ('CRM - ' + (
                        source.entityLabel
                        || this.view.translate(this.view.entityType, 'scopeNames')
                        || this.view.entityType
                    ))
                );
                const template = this.view.translate(
                    'googleCalendarRoutingHintAutoDedicated',
                    'labels',
                    this.view.entityType
                );

                return prefix + template
                    .replace(/\{calendarName\}/g, calendarName)
                    .replace(/\{label\}/g, calendarName);
            }

            return prefix + this.view.translate(
                'googleCalendarRoutingHintPrimary',
                'labels',
                this.view.entityType
            );
        }

        removeRoutingHint() {
            if (!this.view.$el) {
                return;
            }

            this.view.$el.find('.google-calendar-routing-hint').remove();
        }

        toggleShareHelp(visible) {
            this.removeShareHelp();

            if (!visible || !this.view.$el) {
                return;
            }

            const $target = this.view.$el
                .find('[data-name="googleCalendarShareUsers"], [data-name="googleCalendarShareTeams"]')
                .first();

            if (!$target.length) {
                return;
            }

            const text = this.view.translate(
                'googleCalendarShareHelp',
                'labels',
                this.view.entityType
            );

            const $help = $('<div>')
                .addClass('help-block text-muted small google-calendar-share-help margin-bottom')
                .text(text);

            const $row = $target.closest('.row, .panel-body');

            if ($row.length) {
                $row.prepend($help);

                return;
            }

            $target.before($help);
        }

        removeShareHelp() {
            if (!this.view.$el) {
                return;
            }

            this.view.$el.find('.google-calendar-share-help').remove();
        }
    }

    Object.assign(SaveToGoogleHandler.prototype, Bull.Events);

    _exports.default = SaveToGoogleHandler;
});
