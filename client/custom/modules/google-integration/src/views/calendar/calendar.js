define('google-integration:views/calendar/calendar', ['crm:views/calendar/calendar'], function (Dep) {
    'use strict';

    const OVERLAY_SCOPE = 'GoogleCalendarOverlayEvent';

    return Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.dateSourceEntityList = [];
            this.ensureNativeCalendarScopes();
            this.loadDateSourceEntityList();
            this.ensureOverlayScope();
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

                        // Native calendar scopes are fetched via Activities — skip CDS path.
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

            return event;
        },

        fetchEvents(from, to, callback) {
            const scopeList = this.enabledScopeList.filter(scope => this.getAcl().checkScope(scope));
            const wantOverlay = scopeList.includes(OVERLAY_SCOPE);

            const nativeScopes = scopeList.filter(scope =>
                scope !== OVERLAY_SCOPE &&
                !!this.getMetadata().get(['scopes', scope, 'calendar'])
            );

            const cdsScopes = scopeList.filter(scope =>
                scope !== OVERLAY_SCOPE &&
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

            if (nativeScopes.length) {
                let url = 'Activities?from=' + encodeURIComponent(from) +
                    '&to=' + encodeURIComponent(to) +
                    '&scopeList=' + encodeURIComponent(nativeScopes.join(','));

                if (this.options.userId) {
                    url += '&userId=' + encodeURIComponent(this.options.userId);
                }

                if (this.teamIdList && this.teamIdList.length) {
                    url += '&teamIdList=' + encodeURIComponent(this.teamIdList.join(','));
                }

                const agenda = this.mode === 'agendaWeek' || this.mode === 'agendaDay';

                url += '&agenda=' + encodeURIComponent(agenda);

                requests.push(
                    Espo.Ajax.getRequest(url).catch(() => [])
                );
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
