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

        afterRender: function () {
            Dep.prototype.afterRender.call(this);

            // Fill instructions above the form (create only).
            if (this.$el.find('.bug-report-fill-help').length) {
                return;
            }

            const help = this.translate('fillInstructions', 'messages', 'BugReport');
            const $help = $('<div class="alert alert-info bug-report-fill-help" role="note"></div>')
                .html(help);

            this.$el.find('.edit-container, .record').first().prepend($help);

            // Ensure URL / page title stay non-editable in the create UI.
            ['pageUrl', 'pageTitle', 'name'].forEach(name => {
                const view = this.getView('record') && this.getView('record').getFieldView
                    ? this.getView('record').getFieldView(name)
                    : null;

                if (view && typeof view.setReadOnly === 'function') {
                    view.setReadOnly(true);
                }
            });
        },

        afterSave: function () {
            Dep.prototype.afterSave.apply(this, arguments);

            Espo.Ui.success(this.translate('reportSubmitted', 'messages', 'BugReport'));
        },
    });
});
