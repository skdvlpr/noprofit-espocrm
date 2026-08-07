define('bug-tracker:views/modals/report-bug', ['views/modals/edit'], function (Dep) {

    return Dep.extend({

        scope: 'BugReport',

        fullFormDisabled: true,

        layoutName: 'detailSmall',

        sideDisabled: true,

        bottomDisabled: true,

        escapeDisabled: false,

        setup: function () {
            this.scope = 'BugReport';
            this.entityType = 'BugReport';

            const pageUrl = this.options.pageUrl || window.location.href;
            const pageTitle = this.options.pageTitle || (document.title || '');

            this.options.attributes = Espo.Utils.cloneDeep(this.options.attributes || {});
            this.options.attributes.pageUrl = pageUrl;
            this.options.attributes.pageTitle = pageTitle;
            this.options.attributes.status = this.options.attributes.status || 'New';

            Dep.prototype.setup.call(this);

            this.headerHtml = this.translate('ReportBug', 'labels', 'BugReport');
        },

        afterSave: function () {
            Dep.prototype.afterSave.apply(this, arguments);

            Espo.Ui.success(this.translate('reportSubmitted', 'messages', 'BugReport'));
        },
    });
});
