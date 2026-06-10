define('safehouse-crm:views/document/list', ['crm:views/document/list'], function (Dep) {

    return Dep.extend({

        quickDetailDisabled: false,
        quickEditDisabled: false,

        setup() {
            Dep.prototype.setup.apply(this, arguments);

            this.on('after:save', model => {
                const view = this.getView(model.id);

                if (view) {
                    view.reRender();
                }
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.bindQuickViewLinks();
        },

        bindQuickViewLinks() {
            this.$el.off('click.sh-quick-view');

            const nameAttribute = this.getMetadata()
                .get(['clientDefs', this.scope, 'nameAttribute']) || 'name';

            this.$el.on(
                'click.sh-quick-view',
                'td.cell[data-name="' + nameAttribute + '"] a.link',
                e => {
                    if (e.ctrlKey || e.metaKey || e.shiftKey) {
                        return;
                    }

                    const id = $(e.currentTarget).data('id');

                    if (!id || this.quickDetailDisabled) {
                        return;
                    }

                    e.preventDefault();
                    e.stopPropagation();

                    this.actionQuickView({
                        id: id,
                    });
                }
            );
        },
    });
});
