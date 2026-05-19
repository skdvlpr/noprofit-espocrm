define('google-integration:views/calendar/calendar', ['crm:views/calendar/calendar'], function (Dep) {
    'use strict';

    return Dep.extend({
        setup() {
            Dep.prototype.setup.call(this);

            this.dateSourceEntityList = [];
            this.loadDateSourceEntityList();
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

                    if (this.header && this.getView('modeButtons')) {
                        this.getView('modeButtons').scopeList = this.scopeList;
                        this.getView('modeButtons').reRender();
                    }
                })
                .catch(() => {});
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

            if (!scopeList.length) {
                callback([]);
                return;
            }

            if (!this.suppressLoadingAlert) {
                Espo.Ui.notifyWait();
            }

            Espo.Ajax.getRequest('GoogleIntegration/calendar/crm-events', {
                from: from,
                to: to,
                scopeList: scopeList.join(','),
            })
                .then(data => {
                    const events = this.convertToFcEvents(data || []);
                    callback(events);
                    Espo.Ui.notify(false);
                })
                .catch(() => {
                    Dep.prototype.fetchEvents.call(this, from, to, callback);
                });

            this.fetching = true;
            this.suppressLoadingAlert = false;
            setTimeout(() => this.fetching = false, 50);
        },
    });
});
