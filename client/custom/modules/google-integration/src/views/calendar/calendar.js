define('google-integration:views/calendar/calendar', ['crm:views/calendar/calendar'], function (Dep) {
    'use strict';

    const OVERLAY_SCOPE = 'GoogleCalendarOverlayEvent';
    const OWNERSHIP_STORAGE_KEY = 'calendarOwnershipFilter';

    return Dep.extend({
        template: 'google-integration:calendar/calendar',

        overlaySyncInProgress: false,
        ownershipFilter: 'my',

        setup() {
            Dep.prototype.setup.call(this);

            this.dateSourceEntityList = [];
            this.ownershipFilter = this.getStoredOwnershipFilter();
            this.extendedProps = [
                ...(this.extendedProps || []),
                'htmlLink',
                'googleCalendarId',
                'googleEventId',
                'name',
            ];
            this.ensureNativeCalendarScopes();
            this.loadDateSourceEntityList();
            this.ensureOverlayScope();

            this.addActionHandler('syncGoogleOverlay', () => this.actionSyncGoogleOverlay());
            this.addActionHandler('setOwnershipFilter', (e, el) => {
                const filter = el.getAttribute('data-filter') || 'my';
                this.actionSetOwnershipFilter(filter);
            });
        },

        data() {
            const data = Dep.prototype.data.call(this);
            const canTeam = this.canUseTeamOwnershipFilter();

            if (this.ownershipFilter === 'team' && !canTeam) {
                this.ownershipFilter = 'my';
            }

            data.googleOverlaySyncNow = this.translate('googleOverlaySyncNow', 'labels', 'Calendar');
            data.googleOverlaySyncNowTitle = this.translate('googleOverlaySyncNowTitle', 'labels', 'Calendar');
            data.googleOverlaySyncNowHint = this.translate('googleOverlaySyncNowHint', 'labels', 'Calendar');
            data.ownershipFilter = this.ownershipFilter;
            data.showTeamOwnershipFilter = canTeam;
            data.ownershipFilterLabel = this.translate('ownershipFilterLabel', 'labels', 'Calendar');
            data.ownershipFilterMy = this.translate('ownershipFilterMy', 'labels', 'Calendar');
            data.ownershipFilterAvailable = this.translate('ownershipFilterAvailable', 'labels', 'Calendar');
            data.ownershipFilterTeam = this.translate('ownershipFilterTeam', 'labels', 'Calendar');
            data.ownershipFilterMyTitle = this.translate('ownershipFilterMyTitle', 'labels', 'Calendar');
            data.ownershipFilterAvailableTitle = this.translate('ownershipFilterAvailableTitle', 'labels', 'Calendar');
            data.ownershipFilterTeamTitle = this.translate('ownershipFilterTeamTitle', 'labels', 'Calendar');

            return data;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            // Parent builds FullCalendar asynchronously; patch click/edit after it exists.
            const attach = () => {
                if (!this.calendar) {
                    return false;
                }

                this.calendar.setOption('eventClick', info => this.handleEventClick(info));
                this.calendar.setOption('eventAllow', (dropInfo, draggedEvent) => {
                    if ((draggedEvent.extendedProps || {}).scope === OVERLAY_SCOPE) {
                        return false;
                    }

                    return !(draggedEvent.allDay && !dropInfo.allDay || !draggedEvent.allDay && dropInfo.allDay);
                });

                return true;
            };

            if (!attach()) {
                window.setTimeout(() => attach(), 200);
                window.setTimeout(() => attach(), 500);
            }
        },

        canUseTeamOwnershipFilter() {
            const level = this.getAcl().getPermissionLevel('userCalendar');

            return level === 'team' || level === 'all';
        },

        getStoredOwnershipFilter() {
            const value = this.getStorage().get('state', OWNERSHIP_STORAGE_KEY);

            if (value === 'my' || value === 'available' || value === 'team') {
                return value;
            }

            return 'my';
        },

        storeOwnershipFilter(filter) {
            this.getStorage().set('state', OWNERSHIP_STORAGE_KEY, filter);
        },

        actionSetOwnershipFilter(filter) {
            if (filter === 'team' && !this.canUseTeamOwnershipFilter()) {
                return;
            }

            if (!['my', 'available', 'team'].includes(filter)) {
                filter = 'my';
            }

            this.ownershipFilter = filter;
            this.storeOwnershipFilter(filter);

            this.$el.find('[data-action="setOwnershipFilter"]').removeClass('active');
            this.$el.find('[data-action="setOwnershipFilter"][data-filter="' + filter + '"]').addClass('active');

            // Team mode uses Activities teamIdList; clear custom-view team override when switching away.
            if (filter !== 'team') {
                if (!this.isCustomView) {
                    this.teamIdList = null;
                }
            } else {
                this.teamIdList = this.getUserTeamIdList();
            }

            if (this.calendar) {
                this.suppressLoadingAlert = true;
                this.calendar.refetchEvents();
            }
        },

        getUserTeamIdList() {
            const ids = this.getUser().get('teamsIds');

            return Array.isArray(ids) ? ids.filter(Boolean) : [];
        },

        /**
         * Entities with scopes.calendar (e.g. ActivityOfferSlot) must appear even
         * when GoogleIntegration is present — they use native Espo Activities API.
         */
        ensureNativeCalendarScopes() {
            const configured = this.getConfig().get('calendarEntityList') || [];

            (Array.isArray(configured) ? configured : []).forEach(entityType => {
                if (!entityType || !this.getMetadata().get(['scopes', entityType, 'calendar'])) {
                    return;
                }

                if (!this.getAcl().checkScope(entityType)) {
                    return;
                }

                if (!this.scopeList.includes(entityType)) {
                    this.scopeList.push(entityType);
                }

                if (!this.enabledScopeList.includes(entityType)) {
                    this.enabledScopeList.push(entityType);
                }

                const color = this.getMetadata().get(['clientDefs', entityType, 'color'])
                    || this.getMetadata().get(['scopes', entityType, 'color']);

                if (color) {
                    this.colors[entityType] = color;
                }
            });
        },

        ensureOverlayScope() {
            // Overlay is served via dedicated API (filtered by current user), not record ACL.
            if (!this.scopeList.includes(OVERLAY_SCOPE)) {
                this.scopeList.push(OVERLAY_SCOPE);
            }

            if (!this.enabledScopeList.includes(OVERLAY_SCOPE)) {
                this.enabledScopeList.push(OVERLAY_SCOPE);
            }

            this.colors[OVERLAY_SCOPE] = this.colors[OVERLAY_SCOPE]
                || this.getMetadata().get(['clientDefs', OVERLAY_SCOPE, 'color'])
                || '#5c6bc0';
        },

        loadDateSourceEntityList() {
            Espo.Ajax.getRequest('CalendarDateSource', {
                select: 'targetEntityType,calendarViewEnabled,isActive',
                maxSize: 50,
            })
                .then(response => {
                    const list = response.list || [];
                    const entitySet = {};

                    list.forEach(item => {
                        if (!item.isActive || !item.calendarViewEnabled) {
                            return;
                        }

                        if (this.getMetadata().get(['scopes', item.targetEntityType, 'calendar'])) {
                            return;
                        }

                        entitySet[item.targetEntityType] = true;
                    });

                    this.dateSourceEntityList = Object.keys(entitySet)
                        .filter(entityType => this.getAcl().checkScope(entityType));

                    this.dateSourceEntityList.forEach(entityType => {
                        if (!this.scopeList.includes(entityType)) {
                            this.scopeList.push(entityType);
                        }

                        if (!this.enabledScopeList.includes(entityType)) {
                            this.enabledScopeList.push(entityType);
                        }

                        const color = this.getMetadata().get(['clientDefs', entityType, 'color']);

                        if (color) {
                            this.colors[entityType] = color;
                        }
                    });

                    this.ensureNativeCalendarScopes();
                    this.ensureOverlayScope();

                    if (this.header && this.getView('modeButtons')) {
                        this.getView('modeButtons').scopeList = this.scopeList;
                        this.getView('modeButtons').reRender();
                    }
                })
                .catch(() => {
                    this.ensureNativeCalendarScopes();
                });
        },

        /**
         * @param {Object.<string, *>} o
         */
        convertToFcEvent(o) {
            const recordId = o.id;
            const uniqueKey = o.calendarEventKey || o.id;
            const patched = {
                ...o,
                id: uniqueKey,
                recordId: recordId,
            };

            const event = Dep.prototype.convertToFcEvent.call(this, patched);

            event.recordId = recordId;
            event.id = o.scope + '-' + uniqueKey;
            event.editable = o.scope !== OVERLAY_SCOPE;
            event.startEditable = o.scope !== OVERLAY_SCOPE;
            event.durationEditable = o.scope !== OVERLAY_SCOPE;

            // Ensure overlay scalars survive FullCalendar's extendedProps bag.
            event.extendedProps = event.extendedProps || {};
            event.extendedProps.recordId = recordId;
            event.extendedProps.htmlLink = o.htmlLink || null;
            event.extendedProps.googleCalendarId = o.googleCalendarId || null;
            event.extendedProps.googleEventId = o.googleEventId || null;
            event.extendedProps.name = o.name || event.extendedProps.name || null;

            return event;
        },

        handleEventClick(info) {
            const event = info.event;
            const scope = event.extendedProps.scope;
            const recordId = event.extendedProps.recordId;

            if (scope === OVERLAY_SCOPE) {
                this.openOverlayEventModal(event);

                return;
            }

            this.openCoreEventDetail(scope, recordId);
        },

        openCoreEventDetail(scope, recordId) {
            Espo.loader.require('helpers/record-modal', mod => {
                const Helper = mod.default || mod;
                const helper = new Helper();

                helper.showDetail(this, {
                    entityType: scope,
                    id: recordId,
                    removeDisabled: false,
                    beforeSave: () => {
                        if (this.options.onSave) {
                            this.options.onSave();
                        }
                    },
                    beforeDestroy: () => {
                        if (this.options.onSave) {
                            this.options.onSave();
                        }
                    },
                    afterSave: (model, o) => {
                        if (!o.bypassClose) {
                            // modal closes itself
                        }

                        this.updateModel(model);
                    },
                    afterDestroy: model => {
                        this.removeModel(model);
                    },
                });
            });
        },

        openOverlayEventModal(fcEvent) {
            const props = fcEvent.extendedProps || {};
            const htmlLink = props.htmlLink || null;
            const name = fcEvent.title || props.name || '—';
            const whenText = this.formatOverlayWhen(fcEvent, props);

            this.createView('overlayEventModal', 'google-integration:views/calendar/modals/overlay-event', {
                eventName: name,
                whenText: whenText,
                htmlLink: htmlLink,
            }, view => {
                view.render();
            });
        },

        formatOverlayWhen(fcEvent, props) {
            const allDay = !!fcEvent.allDay || !!props.dateStartDate;
            const dateTime = this.getDateTime();

            if (allDay) {
                const start = props.dateStartDate || (fcEvent.start
                    ? dateTime.toMoment(fcEvent.start).format(dateTime.internalDateFormat)
                    : '');
                let end = props.dateEndDate || '';

                if (!end && fcEvent.end) {
                    end = dateTime.toMoment(fcEvent.end).clone().add(-1, 'days')
                        .format(dateTime.internalDateFormat);
                }

                if (start && end && start !== end) {
                    return start + ' – ' + end;
                }

                return start || '—';
            }

            let start = '';
            let end = '';

            if (props.dateStart) {
                start = dateTime.toDisplay(props.dateStart);
            } else if (fcEvent.start) {
                start = dateTime.toMoment(fcEvent.start).format(dateTime.dateTimeFormat);
            }

            if (props.dateEnd) {
                end = dateTime.toDisplay(props.dateEnd);
            } else if (fcEvent.end) {
                end = dateTime.toMoment(fcEvent.end).format(dateTime.dateTimeFormat);
            }

            if (start && end) {
                return start + ' – ' + end;
            }

            return start || end || '—';
        },

        actionSyncGoogleOverlay() {
            if (this.overlaySyncInProgress) {
                return;
            }

            this.overlaySyncInProgress = true;

            const $btn = this.$el.find('[data-action="syncGoogleOverlay"]');
            $btn.addClass('disabled').prop('disabled', true);

            Espo.Ui.notify(this.translate('googleOverlaySyncNowRunning', 'messages', 'Calendar'));

            Espo.Ajax.postRequest('GoogleIntegration/calendar/overlay-sync')
                .then(() => {
                    Espo.Ui.success(this.translate('googleOverlaySyncNowSuccess', 'messages', 'Calendar'));

                    if (this.calendar) {
                        this.suppressLoadingAlert = true;
                        this.calendar.refetchEvents();
                    }
                })
                .catch(() => {
                    Espo.Ui.error(
                        this.translate('googleOverlaySyncNowFailed', 'messages', 'Calendar')
                    );
                })
                .finally(() => {
                    this.overlaySyncInProgress = false;
                    $btn.removeClass('disabled').prop('disabled', false);
                });
        },

        fetchEvents(from, to, callback) {
            // Overlay ignores checkScope (entity ACL is intentionally no).
            const wantOverlay = this.enabledScopeList.includes(OVERLAY_SCOPE);

            const selectableScopes = this.enabledScopeList.filter(scope =>
                scope !== OVERLAY_SCOPE && this.getAcl().checkScope(scope)
            );

            const nativeScopes = selectableScopes.filter(scope =>
                !!this.getMetadata().get(['scopes', scope, 'calendar'])
            );

            const cdsScopes = selectableScopes.filter(scope =>
                !this.getMetadata().get(['scopes', scope, 'calendar'])
            );

            if (!nativeScopes.length && !cdsScopes.length && !wantOverlay) {
                callback([]);

                return;
            }

            if (!this.suppressLoadingAlert) {
                Espo.Ui.notifyWait();
            }

            const requests = [];
            const ownership = this.ownershipFilter || 'my';
            const agenda = this.mode === 'agendaWeek' || this.mode === 'agendaDay';

            if (nativeScopes.length) {
                if (ownership === 'available') {
                    requests.push(
                        Espo.Ajax.getRequest('GoogleIntegration/calendar/available-events', {
                            from: from,
                            to: to,
                            scopeList: nativeScopes.join(','),
                            agenda: agenda ? 'true' : 'false',
                        }).catch(() => [])
                    );
                } else {
                    let url = 'Activities?from=' + encodeURIComponent(from) +
                        '&to=' + encodeURIComponent(to) +
                        '&scopeList=' + encodeURIComponent(nativeScopes.join(','));

                    if (ownership === 'team') {
                        const teamIds = this.getUserTeamIdList();

                        if (teamIds.length) {
                            url += '&teamIdList=' + encodeURIComponent(teamIds.join(','));
                        } else if (this.options.userId) {
                            url += '&userId=' + encodeURIComponent(this.options.userId);
                        }
                    } else if (this.options.userId) {
                        url += '&userId=' + encodeURIComponent(this.options.userId);
                    }

                    if (ownership !== 'team' && this.teamIdList && this.teamIdList.length && this.isCustomView) {
                        url += '&teamIdList=' + encodeURIComponent(this.teamIdList.join(','));
                    }

                    url += '&agenda=' + encodeURIComponent(agenda);

                    requests.push(
                        Espo.Ajax.getRequest(url).catch(() => [])
                    );
                }
            } else {
                requests.push(Promise.resolve([]));
            }

            if (cdsScopes.length) {
                requests.push(
                    Espo.Ajax.getRequest('GoogleIntegration/calendar/crm-events', {
                        from: from,
                        to: to,
                        scopeList: cdsScopes.join(','),
                    }).catch(() => [])
                );
            } else {
                requests.push(Promise.resolve([]));
            }

            if (wantOverlay) {
                requests.push(
                    Espo.Ajax.getRequest('GoogleIntegration/calendar/overlay-events', {
                        from: from,
                        to: to,
                    }).catch(() => [])
                );
            } else {
                requests.push(Promise.resolve([]));
            }

            Promise.all(requests)
                .then(results => {
                    const merged = []
                        .concat(results[0] || [])
                        .concat(results[1] || [])
                        .concat(results[2] || []);
                    const events = this.convertToFcEvents(merged);
                    callback(events);
                    Espo.Ui.notify(false);
                })
                .catch(() => {
                    Dep.prototype.fetchEvents.call(this, from, to, callback);
                });
        },
    });
});
