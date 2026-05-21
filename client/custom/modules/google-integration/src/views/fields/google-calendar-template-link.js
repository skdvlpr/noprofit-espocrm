define('google-integration:views/fields/google-calendar-template-link', ['exports', 'views/fields/link'], function (_exports, _link) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;
    _link = _interopRequireDefault(_link);
    function _interopRequireDefault(e) { return e && e.__esModule ? e : {default: e}; }

    class GoogleCalendarTemplateLinkField extends _link.default {
        getTargetEntityType() {
            if (this.model.entityType === 'CalendarDateSource') {
                return this.model.get('targetEntityType');
            }

            return this.model.entityType;
        }

        getSelectFilters() {
            const entityType = this.getTargetEntityType();

            const filters = {
                isActive: {
                    type: 'equals',
                    attribute: 'isActive',
                    value: true,
                },
            };

            if (!entityType) {
                return filters;
            }

            filters.targetEntityType = {
                type: 'equals',
                attribute: 'targetEntityType',
                value: entityType,
            };

            return filters;
        }

        translateScopeName(entityType) {
            if (!entityType) {
                return '';
            }

            const translated = this.getLanguage().translate(entityType, 'scopeNames');

            if (translated && translated !== entityType) {
                return translated;
            }

            const globalTranslated = this.getLanguage().translate(entityType, 'scopeNames', 'Global');
            return globalTranslated && globalTranslated !== entityType ? globalTranslated : '';
        }

        formatTemplateDisplayName(item) {
            const rawName = item.name || item[this.foreignNameAttribute] || item.id || '';
            const entityType = item.targetEntityType || this.getTargetEntityType();
            const scopeLabel = this.translateScopeName(entityType);

            if (!entityType || !scopeLabel) {
                return rawName;
            }

            const prefixes = [
                entityType + ' — ',
                entityType + ' - ',
                entityType + ' —',
                entityType + ' -',
            ];

            for (const prefix of prefixes) {
                if (rawName.startsWith(prefix)) {
                    return scopeLabel + ' — ' + rawName.slice(prefix.length).trim();
                }
            }

            return scopeLabel + ' — ' + rawName;
        }

        _transformAutocompleteResult(response) {
            const list = [];

            (response.list || []).forEach(item => {
                const displayName = this.formatTemplateDisplayName(item);
                const attributes = {...item};

                if (this.foreignNameAttribute !== 'name') {
                    attributes[this.foreignNameAttribute] = displayName;
                } else {
                    attributes.name = displayName;
                }

                list.push({
                    id: item.id,
                    name: displayName,
                    data: item.id,
                    value: displayName,
                    attributes: attributes,
                });
            });

            return list;
        }

        getCurrentSourceDateType() {
            if (this.model.entityType === 'Opportunity') {
                const selectedDateList = this.model.get('googleCalendarOpportunityDateList');

                if (Array.isArray(selectedDateList) && selectedDateList.length === 1) {
                    return selectedDateList[0];
                }
            }

            return 'main';
        }

        applyTemplateById(templateId) {
            if (!templateId || !this.model.id || this.model.entityType === 'CalendarDateSource') {
                return;
            }

            const sourceDateType = this.getCurrentSourceDateType();

            Espo.Ajax.postRequest(
                `GoogleIntegration/calendar/apply-template/${this.model.entityType}/${this.model.id}`,
                {templateId, sourceDateType}
            ).then(data => {
                if (!data || typeof data !== 'object') {
                    return;
                }

                const mapped = {
                    googleCalendarLocation: data.location ?? '',
                    googleCalendarVisibility: data.visibility ?? 'default',
                    googleCalendarTransparency: data.transparency ?? 'opaque',
                    googleCalendarColorId: String(data.colorId ?? ''),
                    googleCalendarReminderMode: data.reminderMode ?? 'none',
                    googleCalendarReminders: Array.isArray(data.reminders) ? data.reminders : [],
                    googleCalendarDescriptionTemplateOverride: data.description ?? '',
                    googleCalendarId: data.calendarId ?? 'primary',
                };

                this.model.set(mapped, {ui: true});
            }).catch(() => {});
        }

        addLink(id, name) {
            const currentId = this.model.get(this.idName);
            const displayName = this.formatTemplateDisplayName({
                name: name,
                targetEntityType: this.getTargetEntityType(),
            });

            super.addLink(id, displayName);

            if (id && id !== currentId) {
                this.applyTemplateById(id);
            }
        }

        async actionSelect() {
            if (!this.getTargetEntityType()) {
                Espo.Ui.warning(
                    this.translate('googleCalendarSelectTargetEntityFirst', 'labels', 'CalendarDateSource')
                );

                return;
            }

            try {
                await super.actionSelect();
            } catch (e) {
                Espo.Ui.notify(false);

                throw e;
            }
        }

        actionCreateRelated() {
            if (!this.getTargetEntityType()) {
                Espo.Ui.warning(
                    this.translate('googleCalendarSelectTargetEntityFirst', 'labels', 'CalendarDateSource')
                );

                return;
            }

            super.actionCreateRelated();
        }
    }

    _exports.default = GoogleCalendarTemplateLinkField;
});
