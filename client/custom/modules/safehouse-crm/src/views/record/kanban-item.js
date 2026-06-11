define('safehouse-crm:views/record/kanban-item', ['views/record/kanban-item'], function (Dep) {

    const TITLE_FIELDS = new Set(['name']);
    const HERO_FIELDS = new Set(['amount']);
    const DATE_FIELDS = new Set([
        'presentationDate',
        'closeDate',
        'createdAt',
        'modifiedAt',
        'dateStart',
        'dateEnd',
    ]);

    const SHORT_LABEL_KEYS = {
        presentationDate: 'kanbanShortPresentationDate',
        closeDate: 'kanbanShortCloseDate',
        createdAt: 'kanbanShortCreatedAt',
        modifiedAt: 'kanbanShortModifiedAt',
        probability: 'kanbanShortProbability',
    };

    const GOOGLE_DATE_SOURCE_TO_FIELD = {
        presentationDate: 'presentationDate',
        closeDate: 'closeDate',
        main: 'dateStart',
        endDate: 'dateEnd',
    };

    const GOOGLE_DATE_LABEL_KEYS = {
        presentationDate: 'kanbanShortPresentationDate',
        closeDate: 'kanbanShortCloseDate',
        main: 'googleCalendarDateMain',
        endDate: 'googleCalendarDateEnd',
    };

    const FIELD_EMOJI = {
        account: '🏢',
        probability: '📊',
        presentationDate: '📅',
        closeDate: '⏳',
        createdAt: '🕐',
        modifiedAt: '✏️',
        amount: '💰',
    };

    const STAGE_EMOJI = {
        Preparation: '📝',
        Proposal: '📤',
        Negotiation: '🤝',
        'Closed Won': '🎉',
        'Closed Lost': '📉',
    };

    const STAGE_CLASS_PREFIX = 'kanban-stage-';
    const PROB_CLASS_PREFIX = 'kanban-prob-';

    return Dep.extend({

        template: 'safehouse-crm:record/kanban-item',

        events: {
            'click .kanban-title-value a.link': function (e) {
                this.actionOpenQuickView(e);
            },
        },

        actionOpenQuickView(e) {
            if (e.ctrlKey || e.metaKey || e.shiftKey) {
                return;
            }

            e.preventDefault();
            e.stopPropagation();

            let listView = this.getParentView();

            while (listView && typeof listView.actionQuickView !== 'function') {
                listView = listView.getParentView();
            }

            if (!listView || listView.quickDetailDisabled) {
                return;
            }

            listView.actionQuickView({
                id: this.model.id,
            });
        },

        setup() {
            this.itemLayout = this.options.itemLayout;
            this.rowActionsView = this.options.rowActionsView;
            this.rowActionsDisabled = this.options.rowActionsDisabled;
            this.hasStars = this.options.hasStars;

            this.scope = this.options.scope || this.model.entityType;

            this.titleItem = null;
            this.amountItem = null;
            this.statItems = [];
            this.dateItems = [];
            this.layoutDataList = [];
            this.googleCalendarItems = [];
            this.googleEventLinkMap = {};
            this.hasDateItems = false;
            this.hasGoogleCalendarSection = false;
            this.stageInfo = this.getStageInfo();

            this.itemLayout.forEach((item, i) => {
                const name = item.name;
                const key = name + 'Field';
                const fieldType = this.model.getFieldType(name) || 'base';
                const fieldKind = this.resolveFieldKind(name, fieldType, item);

                const row = {
                    name: name,
                    label: this.getKanbanLabel(name),
                    emoji: this.getFieldEmoji(name),
                    isAlignRight: item.align === 'right',
                    isLarge: item.isLarge,
                    isMuted: item.isMuted,
                    isFirst: i === 0,
                    key: key,
                    fieldKind: fieldKind,
                    probabilityTier: fieldKind === 'probability'
                        ? this.getProbabilityTier(this.model.get(name))
                        : null,
                    isTitle: this.isTitleField(name, item),
                    isHero: this.isHeroField(name),
                    isDate: this.isDateField(name, fieldType, item),
                };

                this.layoutDataList.push(row);

                if (row.isTitle) {
                    this.titleItem = row;
                } else if (row.isHero) {
                    this.amountItem = row;
                } else if (row.isDate) {
                    this.dateItems.push(row);
                } else {
                    this.statItems.push(row);
                }

                let viewName = item.view || this.model.getFieldParam(name, 'view');

                if (!viewName) {
                    viewName = this.getFieldManager().getViewName(fieldType);
                }

                let mode = 'list';

                if (item.link) {
                    mode = 'listLink';
                }

                this.createView(key, viewName, {
                    model: this.model,
                    name: name,
                    mode: mode,
                    readOnly: true,
                    noLabel: true,
                    selector: '.field[data-name="' + name + '"]',
                });
            });

            this.hasDateItems = this.dateItems.length > 0;

            this.setupDynamicRefresh();

            if (this.shouldLoadGoogleCalendarSection()) {
                this.wait(true);

                this.refreshGoogleCalendarSection()
                    .finally(() => this.wait(false));
            }

            if (!this.rowActionsDisabled) {
                const acl = {
                    edit: this.getAcl().checkModel(this.model, 'edit'),
                    delete: this.getAcl().checkModel(this.model, 'delete'),
                };

                this.createView('itemMenu', this.rowActionsView, {
                    selector: '.item-menu-container',
                    model: this.model,
                    acl: acl,
                    moveOverRowAction: this.options.moveOverRowAction,
                    statusFieldIsEditable: this.options.statusFieldIsEditable,
                    rowActionHandlers: this.options.rowActionHandlers || {},
                    additionalActionList: this.options.additionalRowActionList,
                    scope: this.scope,
                });
            }

            // Kanban drag/menu updates stage on the model but does not re-render the card.
            this.listenTo(this.model, 'change:stage', () => this.applyStageVisuals());
            this.listenTo(this.model, 'change:probability', () => this.applyProbabilityVisuals());
        },

        setupDynamicRefresh() {
            const googleSourceKeys = [
                'change:googleCalendarDateSourceList',
                'change:saveToGoogleCalendar',
            ].join(' ');

            const googleDateKeys = [
                'change:presentationDate',
                'change:closeDate',
                'change:dateStart',
                'change:dateEnd',
            ].join(' ');

            const assignmentKeys = [
                'change:assignedUserId',
                'change:assignedUserName',
                'change:teamsIds',
                'change:teamsNames',
            ].join(' ');

            this.listenTo(this.model, googleSourceKeys, () => {
                this.scheduleGoogleCalendarRefresh(true);
            });

            this.listenTo(this.model, googleDateKeys, () => {
                this.scheduleGoogleCalendarRefresh(false);
            });

            this.listenTo(this.model, assignmentKeys, () => {
                this.scheduleCardRefresh();
            });
        },

        scheduleGoogleCalendarRefresh(refetchLinks) {
            this._googleRefreshRefetchLinks = this._googleRefreshRefetchLinks || refetchLinks;

            if (this._googleRefreshTimer) {
                clearTimeout(this._googleRefreshTimer);
            }

            this._googleRefreshTimer = setTimeout(() => {
                this._googleRefreshTimer = null;

                const shouldRefetch = !!this._googleRefreshRefetchLinks;
                this._googleRefreshRefetchLinks = false;

                this.refreshGoogleCalendarSection({
                    refetchLinks: shouldRefetch,
                });
            }, 50);
        },

        scheduleCardRefresh() {
            if (!this.isRendered()) {
                return;
            }

            this.reRender();
        },

        refreshGoogleCalendarSection(options) {
            options = options || {};

            const refetchLinks = options.refetchLinks !== false;
            const shouldShow = this.shouldLoadGoogleCalendarSection();

            if (!shouldShow) {
                this.googleCalendarItems = [];
                this.hasGoogleCalendarSection = false;
                this.googleEventLinkMap = {};

                if (options.render !== false && this.isRendered()) {
                    this.reRender();
                }

                return Promise.resolve();
            }

            const rebuild = () => {
                this.buildGoogleCalendarSection();

                if (options.render !== false && this.isRendered()) {
                    this.reRender();
                }
            };

            if (refetchLinks) {
                return this.fetchGoogleEventLinks().then(rebuild);
            }

            rebuild();

            return Promise.resolve();
        },

        refreshAfterExternalSave() {
            return this.refreshGoogleCalendarSection();
        },

        getKanbanLabel(name) {
            const shortKey = SHORT_LABEL_KEYS[name];

            if (shortKey) {
                const shortLabel = this.translate(shortKey, 'labels', this.scope);

                if (shortLabel && shortLabel !== shortKey) {
                    return shortLabel;
                }
            }

            return this.translate(name, 'fields', this.scope);
        },

        getFieldEmoji(name) {
            return FIELD_EMOJI[name] || null;
        },

        hasGoogleCalendarFields() {
            return !!this.getMetadata()
                .get(['entityDefs', this.scope, 'fields', 'saveToGoogleCalendar']);
        },

        shouldLoadGoogleCalendarSection() {
            if (!this.hasGoogleCalendarFields() || !this.model.get('saveToGoogleCalendar')) {
                return false;
            }

            const selected = this.model.get('googleCalendarDateSourceList') || [];

            return selected.length > 0;
        },

        fetchGoogleEventLinks() {
            this.googleEventLinkMap = {};

            return Espo.Ajax.getRequest(
                'GoogleIntegration/calendar/entity-event-links/' + this.scope + '/' + this.model.id
            ).then(response => {
                (response.list || []).forEach(row => {
                    if (row && row.sourceDateType && row.htmlLink) {
                        this.googleEventLinkMap[row.sourceDateType] = row.htmlLink;
                    }
                });
            }).catch(() => {
                this.googleEventLinkMap = {};
            });
        },

        buildGoogleCalendarSection() {
            this.googleCalendarItems = [];
            this.hasGoogleCalendarSection = false;

            const selected = this.model.get('googleCalendarDateSourceList') || [];

            if (!selected.length) {
                return;
            }

            selected.forEach(sourceDateType => {
                const fieldName = GOOGLE_DATE_SOURCE_TO_FIELD[sourceDateType] || sourceDateType;

                this.googleCalendarItems.push({
                    sourceDateType: sourceDateType,
                    name: fieldName,
                    label: this.getGoogleDateSourceLabel(sourceDateType, fieldName),
                    emoji: this.getFieldEmoji(fieldName) || '📅',
                    htmlLink: this.googleEventLinkMap[sourceDateType] || null,
                    dateTooltip: this.formatGoogleDateTooltip(fieldName),
                });
            });

            this.hasGoogleCalendarSection = this.googleCalendarItems.length > 0;
        },

        getGoogleDateSourceLabel(sourceDateType, fieldName) {
            const labelKey = GOOGLE_DATE_LABEL_KEYS[sourceDateType];

            if (labelKey) {
                const shortLabel = this.translate(labelKey, 'labels', this.scope);

                if (shortLabel && shortLabel !== labelKey) {
                    return shortLabel;
                }
            }

            const fieldLabel = this.translate(fieldName, 'fields', this.scope);

            if (fieldLabel && fieldLabel !== fieldName) {
                return fieldLabel;
            }

            return sourceDateType;
        },

        formatGoogleDateTooltip(fieldName) {
            const value = this.model.get(fieldName);

            if (!value) {
                return '';
            }

            const fieldType = this.model.getFieldType(fieldName) || 'date';
            const dateTime = this.getDateTime();

            if (fieldType === 'datetime') {
                const momentValue = dateTime.toMoment(value);
                const momentNow = dateTime.toMoment(dateTime.getToday()).startOf('day');
                const ranges = {
                    today: [momentNow.unix(), momentNow.clone().add(1, 'days').unix()],
                    tomorrow: [momentNow.clone().add(1, 'days').unix(), momentNow.clone().add(2, 'days').unix()],
                    yesterday: [momentNow.clone().add(-1, 'days').unix(), momentNow.unix()],
                };
                const unix = momentValue.unix();
                const timeFormat = dateTime.timeFormat;

                if (unix >= ranges.today[0] && unix < ranges.today[1]) {
                    return this.translate('Today') + ' ' + momentValue.format(timeFormat);
                }

                if (unix >= ranges.tomorrow[0] && unix < ranges.tomorrow[1]) {
                    return this.translate('Tomorrow') + ' ' + momentValue.format(timeFormat);
                }

                if (unix >= ranges.yesterday[0] && unix < ranges.yesterday[1]) {
                    return this.translate('Yesterday') + ' ' + momentValue.format(timeFormat);
                }

                const readableFormat = dateTime.getReadableDateFormat();

                if (momentValue.format('YYYY') === momentNow.format('YYYY')) {
                    return momentValue.format(readableFormat) + ' ' + momentValue.format(timeFormat);
                }

                return momentValue.format(readableFormat + ', YYYY') + ' ' + momentValue.format(timeFormat);
            }

            const momentValue = dateTime.toMoment(value).startOf('day');
            const momentToday = dateTime.toMoment(dateTime.getToday()).startOf('day');
            const diffDays = momentValue.diff(momentToday, 'days');

            if (diffDays === 0) {
                return this.translate('Today');
            }

            if (diffDays === 1) {
                return this.translate('Tomorrow');
            }

            if (diffDays === -1) {
                return this.translate('Yesterday');
            }

            const readableFormat = dateTime.getReadableDateFormat();

            if (momentValue.format('YYYY') === momentToday.format('YYYY')) {
                return momentValue.format(readableFormat);
            }

            return momentValue.format(readableFormat + ', YYYY');
        },

        getAssignedUserHtml() {
            let id = this.model.get('assignedUserId');
            let name = this.model.get('assignedUserName');
            const assignedUser = this.model.get('assignedUser');

            if (assignedUser && typeof assignedUser === 'object') {
                id = id || assignedUser.id;
                name = name || assignedUser.name;
            }

            if (!id && !name) {
                return '';
            }

            const text = this.escapeString(name || id || '');

            if (!id) {
                return text;
            }

            const safeId = this.escapeString(id);

            return '<a href="#User/view/' + safeId + '" data-id="' + safeId + '" data-scope="User">' + text + '</a>';
        },

        getTeamsHtml() {
            const ids = this.model.get('teamsIds');

            if (!Array.isArray(ids) || !ids.length) {
                return '';
            }

            const names = this.model.get('teamsNames') || {};

            return ids.map(id => {
                const name = this.escapeString(names[id] || id);

                return '<span class="label team-label">' + name + '</span>';
            }).join('');
        },

        getStageInfo() {
            const stage = this.model.get('stage');

            if (!stage) {
                return null;
            }

            const styleMap = this.getMetadata()
                .get(['entityDefs', this.scope, 'fields', 'stage', 'style']) || {};

            const style = styleMap[stage] || 'default';
            const emoji = STAGE_EMOJI[stage] || '💼';

            const label = this.getLanguage().translateOption(stage, 'stage', this.scope) || stage;

            return {
                stage: stage,
                style: style,
                emoji: emoji,
                label: label,
            };
        },

        getProbabilityTier(value) {
            if (value === null || value === undefined || value === '') {
                return 'default';
            }

            const numeric = Number(value);

            if (Number.isNaN(numeric)) {
                return 'default';
            }

            if (numeric >= 80) {
                return 'success';
            }

            if (numeric >= 50) {
                return 'warning';
            }

            if (numeric >= 20) {
                return 'primary';
            }

            return 'default';
        },

        applyStageVisuals() {
            this.stageInfo = this.getStageInfo();

            const $card = this.$el.find('.safehouse-kanban-card').first();

            if (!$card.length) {
                return;
            }

            this.stripPrefixedClasses($card, STAGE_CLASS_PREFIX);

            if (this.stageInfo && this.stageInfo.style) {
                $card.addClass(STAGE_CLASS_PREFIX + this.stageInfo.style);
            }

            const $chip = $card.find('.kanban-stage-chip').first();

            if (!$chip.length || !this.stageInfo) {
                return;
            }

            $chip.attr('title', this.stageInfo.label);
            $chip.attr('aria-label', this.stageInfo.label);
            $chip.find('.kanban-stage-chip-emoji').first().text(this.stageInfo.emoji);
        },

        applyProbabilityVisuals() {
            const tier = this.getProbabilityTier(this.model.get('probability'));
            const $cell = this.$el.find('.kanban-prop-value-probability').first();

            if (!$cell.length) {
                return;
            }

            this.stripPrefixedClasses($cell, PROB_CLASS_PREFIX);
            $cell.addClass('kanban-prob-pill ' + PROB_CLASS_PREFIX + tier);
        },

        stripPrefixedClasses($element, prefix) {
            const className = $element.attr('class') || '';
            const stripped = className
                .split(/\s+/)
                .filter(name => name && !name.startsWith(prefix))
                .join(' ');

            $element.attr('class', stripped);
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            const shortenDatetime = name => {
                if (this.model.getFieldType(name) !== 'datetime') {
                    return;
                }

                const value = this.model.get(name);

                if (!value) {
                    return;
                }

                const dateTime = this.getDateTime();
                const dateOnly = dateTime
                    .toMoment(value)
                    .format(dateTime.getReadableShortDateFormat());

                this.$el
                    .find('.kanban-date-value[data-name="' + name + '"]')
                    .text(dateOnly);
            };

            this.dateItems.forEach(item => shortenDatetime(item.name));
        },

        isTitleField(name, layoutItem) {
            return TITLE_FIELDS.has(name) && layoutItem.link === true;
        },

        isHeroField(name) {
            return HERO_FIELDS.has(name);
        },

        isDateField(name, fieldType, layoutItem) {
            return DATE_FIELDS.has(name)
                || fieldType === 'date'
                || fieldType === 'datetime'
                || layoutItem.isMuted === true;
        },

        resolveFieldKind(name, fieldType, layoutItem) {
            if (layoutItem.link) {
                return 'link';
            }

            if (name === 'amount' || fieldType === 'currency') {
                return 'currency';
            }

            if (name === 'probability') {
                return 'probability';
            }

            if (this.isDateField(name, fieldType, layoutItem)) {
                return 'date';
            }

            if (name === 'account' || fieldType === 'link') {
                return 'link';
            }

            return 'text';
        },

        data() {
            const assignedUserHtml = this.getAssignedUserHtml();
            const teamsHtml = this.getTeamsHtml();

            return {
                layoutDataList: this.layoutDataList,
                rowActionsDisabled: this.rowActionsDisabled,
                isStarred: this.hasStars && this.model.attributes.isStarred,
                entityType: this.model.entityType,
                scope: this.scope,
                titleItem: this.titleItem,
                amountItem: this.amountItem,
                statItems: this.statItems,
                dateItems: this.dateItems,
                hasStatItems: this.statItems.length > 0,
                hasDateItems: this.hasDateItems,
                googleCalendarItems: this.googleCalendarItems,
                stageInfo: this.stageInfo,
                stageStyle: this.stageInfo ? this.stageInfo.style : null,
                stageEmoji: this.stageInfo ? this.stageInfo.emoji : null,
                stageLabel: this.stageInfo ? this.stageInfo.label : null,
                heroEmoji: this.getFieldEmoji('amount'),
                hasGoogleCalendarSection: this.hasGoogleCalendarSection,
                googleCalendarSectionLabel: this.translate('kanbanGoogleCalendar', 'labels', this.scope),
                datesSectionLabel: this.translate('kanbanDatesSection', 'labels', this.scope),
                assignmentSectionLabel: this.translate('kanbanAssignmentSection', 'labels', this.scope),
                hasAssignmentSection: !!(assignedUserHtml || teamsHtml),
                hasAssignedUser: !!assignedUserHtml,
                hasTeams: !!teamsHtml,
                assignedUserHtml: assignedUserHtml,
                teamsHtml: teamsHtml,
                assignedUserLabel: this.translate('assignedUser', 'fields', this.scope),
                teamsLabel: this.translate('teams', 'fields', this.scope),
            };
        },
    });
});
