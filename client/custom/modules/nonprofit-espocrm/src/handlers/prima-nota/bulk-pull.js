define('nonprofit-espocrm:handlers/prima-nota/bulk-pull', [], function () {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        resolveRecordView() {
            const view = this.view;

            if (view && typeof view.getRecordView === 'function') {
                const recordView = view.getRecordView();

                if (recordView) {
                    return recordView;
                }
            }

            return view;
        }

        resolveCollection() {
            const recordView = this.resolveRecordView();

            if (recordView && recordView.collection) {
                return recordView.collection;
            }

            if (this.view && this.view.collection) {
                return this.view.collection;
            }

            return null;
        }

        /**
         * Refresh PrimaNota list collection (not full page reload).
         * Always returns a Promise so callers can wait before closing the modal.
         */
        refreshList() {
            const recordView = this.resolveRecordView();
            const collection = this.resolveCollection();

            if (!collection || typeof collection.fetch !== 'function') {
                return Promise.reject(new Error('PrimaNota collection not available'));
            }

            // Force a fresh page-1 fetch so newly imported rows appear.
            if (typeof collection.offset !== 'undefined') {
                collection.offset = 0;
            }

            return Promise.resolve(collection.fetch({
                more: false,
            }))
                .then(() => {
                    if (recordView && typeof recordView.loadReportingStats === 'function') {
                        try {
                            recordView.loadReportingStats();
                        }
                        catch (e) {
                            // non-fatal
                        }
                    }

                    if (recordView && typeof recordView.applyAmountColors === 'function' &&
                        recordView.isRendered && recordView.isRendered()
                    ) {
                        try {
                            recordView.applyAmountColors();
                        }
                        catch (e) {
                            // non-fatal
                        }
                    }
                });
        }

        open() {
            const view = this.view;

            view.createView('dialog', 'nonprofit-espocrm:views/prima-nota/modals/bulk-pull', {
                collection: this.resolveCollection(),
            }, dialog => {
                dialog.on('done', (response, afterRefresh) => {
                    const finish = typeof afterRefresh === 'function' ? afterRefresh : () => {};

                    this.refreshList()
                        .then(() => finish(null))
                        .catch(err => finish(err && err.message ? err.message : err));
                });

                dialog.render();
            });
        }
    }

    return Handler;
});
