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

            if (this.$el.find('.bug-report-fill-help').length) {
                return;
            }

            const title = this.translate('fillInstructionsTitle', 'messages', 'BugReport');
            const step1 = this.translate('fillInstructionsStep1', 'messages', 'BugReport');
            const step2 = this.translate('fillInstructionsStep2', 'messages', 'BugReport');
            const step3 = this.translate('fillInstructionsStep3', 'messages', 'BugReport');
            const note = this.translate('fillInstructionsNote', 'messages', 'BugReport');

            const $help = $(
                '<div class="alert alert-info bug-report-fill-help" role="note">' +
                    '<div class="bug-report-fill-help-title"></div>' +
                    '<ol class="bug-report-fill-help-list">' +
                        '<li></li><li></li><li></li>' +
                    '</ol>' +
                    '<p class="bug-report-fill-help-note"></p>' +
                '</div>'
            );

            $help.find('.bug-report-fill-help-title').text(title);
            $help.find('li').eq(0).html(step1);
            $help.find('li').eq(1).html(step2);
            $help.find('li').eq(2).html(step3);
            $help.find('.bug-report-fill-help-note').text(note);

            const $host = this.$el.find('.edit-container, .record, .modal-body').first();

            if ($host.length) {
                $host.prepend($help);
            } else {
                this.$el.prepend($help);
            }

            ['pageUrl', 'pageTitle', 'name'].forEach(name => {
                const record = this.getView('record');
                const view = record && typeof record.getFieldView === 'function'
                    ? record.getFieldView(name)
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
