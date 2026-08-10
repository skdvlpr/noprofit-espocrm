define('google-integration:views/fields/google-calendar-share-calendar-id', [
    'exports',
    'google-integration:views/fields/google-calendar-id',
], function (_exports, Dep) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    Dep = Dep && Dep.__esModule ? Dep : {default: Dep};

    /**
     * Calendar picker/create against a consented share-target Google account.
     */
    class GoogleCalendarShareCalendarIdField extends Dep.default {
        setup() {
            super.setup();

            this.affixesLoaded = false;
            this.loadNamingAffixes();

            this.listenTo(this.model, 'change:googleCalendarShareCalendarUserId', () => {
                this.calendarLoaded = false;
                this.calendarOptionList = [];
                this.model.set(this.name, 'primary', {ui: true});
                this.reloadCalendars();
            });
        }

        getTargetUserId() {
            return String(this.model.get('googleCalendarShareCalendarUserId') || '').trim();
        }

        /**
         * Load prefix/suffix even before an owner is chosen (team-only share case).
         */
        loadNamingAffixes() {
            Espo.Ajax.getRequest('GoogleIntegration/calendar/share-picker-data')
                .then(data => {
                    if (typeof data.namePrefix === 'string' && data.namePrefix.trim() !== '') {
                        this.calendarNamePrefix = data.namePrefix;
                    }

                    if (typeof data.nameSuffix === 'string') {
                        this.calendarNameSuffix = data.nameSuffix;
                    }

                    this.affixesLoaded = true;

                    if (this.isRendered() && this.mode === 'edit') {
                        this.renderCreateNewUi();
                        this.syncCreateNewUiState();
                    }
                })
                .catch(() => {
                    this.affixesLoaded = true;
                });
        }

        loadCalendars() {
            const userId = this.getTargetUserId();

            if (!userId) {
                this.calendarLoadPending = false;
                this.calendarLoaded = true;
                this.calendarOptionList = [{id: 'primary', summary: 'primary'}];
                this.applyCalendarOptions();
                this.renderNeedsUserHint();

                if (this.isRendered() && this.mode === 'edit') {
                    this.renderCreateNewUi();
                    this.syncCreateNewUiState();
                }

                return;
            }

            if (this.calendarLoadPending) {
                return;
            }

            this.calendarLoadPending = true;
            this.removeNeedsUserHint();

            Espo.Ajax.getRequest('GoogleIntegration/calendar/share-target-calendars', {userId: userId})
                .then(data => {
                    this.calendarLoadPending = false;

                    if (typeof data.namePrefix === 'string' && data.namePrefix.trim() !== '') {
                        this.calendarNamePrefix = data.namePrefix;
                    }

                    if (typeof data.nameSuffix === 'string') {
                        this.calendarNameSuffix = data.nameSuffix;
                    }

                    if (this.isRendered() && this.mode === 'edit') {
                        this.renderCreateNewUi();
                        this.syncCreateNewUiState();
                    }

                    const list = Array.isArray(data.list) ? data.list : [];
                    this.calendarOptionList = list.length
                        ? list.map(item => ({
                            id: String(item.id || ''),
                            summary: String(item.summary || item.id || ''),
                        })).filter(item => item.id !== '')
                        : [{id: 'primary', summary: 'primary'}];

                    this.calendarLoaded = true;
                    this.applyCalendarOptions();
                })
                .catch(() => {
                    this.calendarLoadPending = false;
                    this.calendarLoaded = true;
                    this.calendarOptionList = [{id: 'primary', summary: 'primary'}];
                    this.applyCalendarOptions();

                    if (this.isRendered() && this.mode === 'edit') {
                        this.renderCreateNewUi();
                        this.syncCreateNewUiState();
                    }
                });
        }

        submitCreateCalendar() {
            if (this.createInProgress) {
                return;
            }

            const userId = this.getTargetUserId();

            if (!userId) {
                Espo.Ui.error(
                    this.translateLabelKey(
                        'googleCalendarSharePickNeedsUser',
                        'Select a shared calendar owner first.'
                    )
                );

                return;
            }

            const label = this.stripAffixes(
                String(this.newCalendarName || this.$el.find('[data-role="new-calendar-name"]').val() || '')
            );

            if (!label) {
                Espo.Ui.error(this.translateLabelKey('googleCalendarCreateNewNameRequired'));

                return;
            }

            this.createInProgress = true;
            Espo.Ui.notify(this.translate('pleaseWait', 'messages'));

            Espo.Ajax.postRequest('GoogleIntegration/calendar/share-target-calendars', {
                userId: userId,
                label: label,
            }).then(response => {
                this.createInProgress = false;
                Espo.Ui.notify(false);

                const id = String(response.id || '');
                const name = String(response.summary || this.buildNamingPatternPreview(label));

                if (!id) {
                    Espo.Ui.error(this.translateLabelKey('googleCalendarCreateNewFailed'));

                    return;
                }

                this.calendarOptionList = this.calendarOptionList.filter(item => item.id !== id);
                this.calendarOptionList.unshift({id: id, summary: name});
                this.calendarLoaded = true;
                this.createNewCalendar = false;
                this.newCalendarName = '';
                this.model.set(this.name, id);
                this.applyCalendarOptions();
                this.renderCreateNewUi();
                this.syncCreateNewUiState();

                Espo.Ui.success(
                    response.created
                        ? this.translateLabelKey('googleCalendarCreateNewSuccess')
                        : this.translateLabelKey('googleCalendarCreateNewExists')
                );
            }).catch(() => {
                this.createInProgress = false;
                Espo.Ui.notify(false);
                Espo.Ui.error(this.translateLabelKey('googleCalendarCreateNewFailed'));
            });
        }

        afterRender() {
            super.afterRender();
            this.renderNeedsUserHint();
        }

        renderNeedsUserHint() {
            if (!this.$el || this.mode !== 'edit') {
                return;
            }

            this.removeNeedsUserHint();

            if (this.getTargetUserId()) {
                return;
            }

            let text = this.translate('googleCalendarSharePickNeedsUser', 'labels', this.entityType);

            if (!text || text === 'googleCalendarSharePickNeedsUser') {
                text = this.translate('googleCalendarSharePickNeedsUser', 'labels', 'Global');
            }

            this.$el.append(
                $('<div>')
                    .addClass('gi-share-calendar-needs-user help-block text-warning small')
                    .text(text)
            );
        }

        removeNeedsUserHint() {
            if (this.$el) {
                this.$el.find('.gi-share-calendar-needs-user').remove();
            }
        }
    }

    _exports.default = GoogleCalendarShareCalendarIdField;
});
