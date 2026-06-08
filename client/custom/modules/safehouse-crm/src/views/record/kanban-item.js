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

            this.itemLayout.forEach((item, i) => {
                const name = item.name;
                const key = name + 'Field';
                const fieldType = this.model.getFieldType(name) || 'base';
                const fieldKind = this.resolveFieldKind(name, fieldType, item);

                const row = {
                    name: name,
                    label: this.getKanbanLabel(name),
                    isAlignRight: item.align === 'right',
                    isLarge: item.isLarge,
                    isMuted: item.isMuted,
                    isFirst: i === 0,
                    key: key,
                    fieldKind: fieldKind,
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
            };
        },
    });
});
