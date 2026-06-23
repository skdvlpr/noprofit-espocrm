define('nonprofit-espocrm:lib/quick-view-navigation', [], function () {

    const ensureEnabled = view => {
        view.quickDetailDisabled = false;
        view.quickEditDisabled = false;
    };

    const shouldBypass = e => e.ctrlKey || e.metaKey || e.shiftKey;

    const openQuickView = (view, e) => {
        const $target = $(e.currentTarget);
        const id = $target.data('id');

        if (!id || view.quickDetailDisabled) {
            return false;
        }

        e.preventDefault();
        e.stopPropagation();

        view.actionQuickView({
            id: id,
            scope: $target.data('scope') || undefined,
        });

        return true;
    };

    const patchListLinkClick = view => {
        if (view._safehouseQuickViewListPatched) {
            return;
        }

        view._safehouseQuickViewListPatched = true;

        const originalProcessLinkClick = view.processLinkClick.bind(view);

        view.processLinkClick = function (id) {
            if (!this.quickDetailDisabled && !this.selectable && this.scope && id) {
                this.actionQuickView({
                    id: id,
                });

                return;
            }

            originalProcessLinkClick(id);
        };
    };

    const patchKanbanLinkClick = view => {
        const original = view.events['click a.link'];

        view.events['click a.link'] = function (e) {
            if (shouldBypass(e)) {
                return;
            }

            if (!this.quickDetailDisabled && this.scope && !this.selectable) {
                const id = $(e.currentTarget).data('id');

                if (id && openQuickView(this, e)) {
                    return;
                }
            }

            if (typeof original === 'function') {
                original.call(this, e);
            }
        };
    };

    const bindAfterSaveRefresh = view => {
        if (view._safehouseQuickViewAfterSaveBound) {
            return;
        }

        view._safehouseQuickViewAfterSaveBound = true;

        view.on('after:save', model => {
            const rowView = view.getView(model.id);

            if (rowView) {
                rowView.reRender();
            }
        });
    };

    return {
        ensureEnabled,
        patchListLinkClick,
        patchKanbanLinkClick,
        bindAfterSaveRefresh,
    };
});
