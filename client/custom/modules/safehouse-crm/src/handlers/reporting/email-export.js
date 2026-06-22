define('safehouse-crm:handlers/reporting/email-export', [], function () {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        send() {
            const listView = this.resolveRecordListView();

            if (typeof listView.openReportingEmailExport === 'function') {
                listView.openReportingEmailExport('csv-email');

                return;
            }

            Espo.Ui.error('Reporting export is not available on this view.');
        }

        /**
         * Menu handler runs on views/list; checkboxes live on child views/record/list.
         */
        resolveRecordListView() {
            const view = this.view;

            if (Array.isArray(view.checkedList)) {
                return view;
            }

            if (typeof view.getRecordView === 'function') {
                const recordView = view.getRecordView();

                if (recordView) {
                    return recordView;
                }
            }

            return view;
        }
    }

    return Handler;
});
