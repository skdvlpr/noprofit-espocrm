define('nonprofit-espocrm:views/activity-offer-slot/record/detail', [
    'views/record/detail',
    'helpers/action-item-setup',
], function (Dep, ActionItemSetup) {

    ActionItemSetup = ActionItemSetup.default || ActionItemSetup;

    return Dep.extend({

        /**
         * Espo 10 ignores legacy clientDefs.menu.detail.buttons.
         * Load Cancel shift as a visible header button.
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
    });
});
