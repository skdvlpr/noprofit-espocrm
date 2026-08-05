define('nonprofit-espocrm:views/activity-offer/record/detail', [
    'views/record/detail',
    'nonprofit-espocrm:views/activity-offer/record/place-description-layout',
    'helpers/action-item-setup',
], function (Dep, PlaceDescriptionLayout, ActionItemSetup) {

    ActionItemSetup = ActionItemSetup.default || ActionItemSetup;

    return Dep.extend(Object.assign({}, PlaceDescriptionLayout, {

        template: 'nonprofit-espocrm:activity-offer/record/detail',

        bottomView: 'nonprofit-espocrm:views/activity-offer/record/detail-bottom',

        planningView: 'nonprofit-espocrm:views/activity-offer/record/detail-planning',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupPlaceDescriptionLayout();
        },

        /**
         * Espo 10 ignores legacy clientDefs.menu.detail.buttons.
         * Load lifecycle actions as visible header buttons (next to Edit).
         */
        setupActionItems: function () {
            Dep.prototype.setupActionItems.call(this);

            if (this.buttonsDisabled || this.type !== this.TYPE_DETAIL) {
                return;
            }

            const actionItemSetup = new ActionItemSetup();

            actionItemSetup.setup({
                view: this,
                type: 'detailButtonList',
                waitFunc: promise => this.wait(promise),
                addFunc: item => this.addButton(item),
                showFunc: name => this.showActionItem(name),
                hideFunc: name => this.hideActionItem(name),
                enableFunc: name => this.enableActionItem(name),
                disableFunc: name => this.disableActionItem(name),
            });
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.afterRenderPlaceDescriptionLayout();
        },

        createBottomView: function () {
            this.createPlanningView();

            const el = this.getSelector() || '#' + this.id;

            this.createView('bottom', this.bottomView, {
                model: this.model,
                scope: this.scope,
                fullSelector: el + ' .activity-offer-bottom-full',
                readOnly: this.readOnly,
                type: this.type,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this,
                portalLayoutDisabled: this.portalLayoutDisabled,
                isReturn: this.options.isReturn,
                dataObject: this.dataObject,
            });
        },

        createPlanningView: function () {
            const el = this.getSelector() || '#' + this.id;

            this.createView('planning', this.planningView, {
                model: this.model,
                scope: this.scope,
                fullSelector: el + ' .activity-offer-planning-top',
                readOnly: this.readOnly,
                type: this.type,
                inlineEditDisabled: this.inlineEditDisabled,
                recordHelper: this.recordHelper,
                recordViewObject: this,
                portalLayoutDisabled: this.portalLayoutDisabled,
                isReturn: this.options.isReturn,
                dataObject: this.dataObject,
            });
        },
    }));
});
