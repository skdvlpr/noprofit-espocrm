/**
 * BugTracker FAB — floating bug button on every authenticated CRM page.
 */
(function () {
    const SCOPE = 'BugReport';
    const BUTTON_ID = 'bug-tracker-fab';

    const openReportModal = function (view) {
        const pageUrl = window.location.href;
        const pageTitle = (document.title || '').trim();

        view.createView('bugTrackerReportDialog', 'bug-tracker:views/modals/report-bug', {
            pageUrl: pageUrl,
            pageTitle: pageTitle,
        }, dialog => {
            dialog.render();
        });
    };

    const isEnabled = function (view) {
        if (!view.getConfig) {
            return true;
        }

        return view.getConfig().get('bugTrackerEnabled') !== false;
    };

    const ensureFab = function (view) {
        const existing = document.getElementById(BUTTON_ID);

        if (!isEnabled(view) || !view.getAcl || !view.getAcl().check(SCOPE, 'create')) {
            if (existing) {
                existing.remove();
            }

            return;
        }

        if (existing) {
            return;
        }

        const btn = document.createElement('button');
        btn.type = 'button';
        btn.id = BUTTON_ID;
        btn.className = 'bug-tracker-fab';
        btn.setAttribute('aria-label', view.translate('fabTooltip', 'messages', SCOPE));
        btn.title = view.translate('fabTooltip', 'messages', SCOPE);
        btn.innerHTML = '<span class="fas fa-bug" aria-hidden="true"></span>';

        btn.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            openReportModal(view);
        });

        document.body.appendChild(btn);
    };

    Espo.loader.requirePromise('views/site/master')
        .then(MasterView => {
            const originalAfterRender = MasterView.prototype.afterRender;

            MasterView.prototype.afterRender = function () {
                if (typeof originalAfterRender === 'function') {
                    originalAfterRender.call(this);
                }

                ensureFab(this);
            };
        });
})();
