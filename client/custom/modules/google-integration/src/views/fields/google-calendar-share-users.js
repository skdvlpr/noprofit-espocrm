define('google-integration:views/fields/google-calendar-share-users', [
    'exports',
    'views/fields/link-multiple',
], function (_exports, Dep) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    Dep = Dep && Dep.__esModule ? Dep : {default: Dep};

    /**
     * User multi-select limited to Google Calendar–connected accounts.
     */
    class GoogleCalendarShareUsersField extends Dep.default {
        selectPrimaryFilterName = 'active';
        createDisabled = true;

        setup() {
            super.setup();

            this.pickerData = null;
            this.pickerDataPromise = null;

            this.loadPickerData();
        }

        getSelectBoolFilterList() {
            return ['googleCalendarConnected'];
        }

        getSelectPrimaryFilterName() {
            return 'active';
        }

        loadPickerData() {
            if (this.pickerDataPromise) {
                return this.pickerDataPromise;
            }

            this.pickerDataPromise = Espo.Ajax
                .getRequest('GoogleIntegration/calendar/share-picker-data')
                .then(data => {
                    this.pickerData = data || {users: [], connectedUserIds: [], teams: []};

                    if (this.isRendered()) {
                        this.renderShareHint();
                    }

                    return this.pickerData;
                })
                .catch(() => {
                    this.pickerData = {users: [], connectedUserIds: [], teams: []};

                    return this.pickerData;
                });

            return this.pickerDataPromise;
        }

        afterRender() {
            super.afterRender();
            this.renderShareHint();
        }

        renderShareHint() {
            if (!this.$el || !this.isEditMode()) {
                return;
            }

            this.$el.find('.gi-share-picker-hint').remove();

            const count = (this.pickerData && this.pickerData.connectedUserIds)
                ? this.pickerData.connectedUserIds.length
                : null;

            let text = this.translate('googleCalendarShareUsersHint', 'labels', this.entityType);

            if (!text || text === 'googleCalendarShareUsersHint') {
                text = this.translate('googleCalendarShareUsersHint', 'labels', 'Global');
            }

            if (count === 0) {
                const empty = this.translate('googleCalendarShareUsersEmpty', 'labels', this.entityType);

                text = (!empty || empty === 'googleCalendarShareUsersEmpty')
                    ? this.translate('googleCalendarShareUsersEmpty', 'labels', 'Global')
                    : empty;
            }

            const $hint = $('<div>')
                .addClass('gi-share-picker-hint help-block text-muted small')
                .text(text);

            this.$el.append($hint);
        }
    }

    _exports.default = GoogleCalendarShareUsersField;
});
