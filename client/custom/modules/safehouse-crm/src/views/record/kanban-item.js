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

            this.dateItems.forEach(item => {
                if (this.model.getFieldType(item.name) !== 'datetime') {
                    return;
                }

                const value = this.model.get(item.name);

                if (!value) {
                    return;
                }

                const dateTime = this.getDateTime();
                const dateOnly = dateTime
                    .toMoment(value)
                    .format(dateTime.getReadableShortDateFormat());

                this.$el
                    .find('.kanban-date-value[data-name="' + item.name + '"]')
                    .text(dateOnly);
            });
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
                hasDateItems: this.dateItems.length > 0,
                stageInfo: this.stageInfo,
                stageStyle: this.stageInfo ? this.stageInfo.style : null,
                stageEmoji: this.stageInfo ? this.stageInfo.emoji : null,
                stageLabel: this.stageInfo ? this.stageInfo.label : null,
                heroEmoji: this.getFieldEmoji('amount'),
            };
        },
    });
});
