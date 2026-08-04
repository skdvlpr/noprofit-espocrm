define('nonprofit-espocrm:handlers/reporting/email-export', [], function () {

    class Handler {

        constructor(view) {
            this.view = view;
        }

        send() {
            const listView = this.resolveRecordListView();

            if (typeof listView.openReportingEmailExport === 'function') {
                listView.openReportingEmailExport('xlsx-email');

                return;
            }

            Espo.Ui.error('Email export is not available on this view.');
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
