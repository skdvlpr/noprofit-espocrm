(function () {
    Espo.loader.requirePromise('helpers/model/defaults-populator')
        .then(DefaultsPopulator => {
            DefaultsPopulator.prototype.toFillAssignedUser = function () {
                if (this.user.isPortal()) {
                    return false;
                }

                return true;
            };
        });

    /**
     * Footer branding. Espo mounts `views/site/footer` from a precompiled
     * template before async scriptList patches run, and `html/main.html`
     * also ships a static footer. Force the GoMercato line in all layers.
     *
     * Keep ≥2 case-insensitive "espocrm" occurrences so Espo's AGPL
     * afterRender credit check does not wipe the footer.
     */
    const footerHtml = function (year) {
        return (
            '<p class="credit small">&copy; ' + year +
            ' <a href="https://www.espocrm.com" title="Powered by EspoCRM"' +
            ' rel="noopener" target="_blank" tabindex="-1">EspoCRM, Inc.</a>' +
            ' | <a href="https://gomercato.it/" title="Super powered by GoMercato.it"' +
            ' rel="noopener" target="_blank" tabindex="-1">Super powered by GoMercato.it</a>' +
            ' 🚀</p>'
        );
    };

    const applyFooterDom = function () {
        const html = footerHtml((new Date()).getFullYear());

        document.querySelectorAll('body > footer').forEach(el => {
            if (!el.querySelector('a[href="https://gomercato.it/"]')) {
                el.innerHTML = html;
            }
        });
    };

    applyFooterDom();

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', applyFooterDom);
    }

    // After Espo site view render / AGPL credit check.
    [0, 300, 1000, 2500].forEach(ms => window.setTimeout(applyFooterDom, ms));

    Espo.loader.requirePromise('views/site/footer')
        .then(FooterView => {
            FooterView.prototype.templateContent =
                '<p class="credit small">&copy; {{year}}' +
                ' <a href="https://www.espocrm.com" title="Powered by EspoCRM"' +
                ' rel="noopener" target="_blank" tabindex="-1">EspoCRM, Inc.</a>' +
                ' | <a href="https://gomercato.it/" title="Super powered by GoMercato.it"' +
                ' rel="noopener" target="_blank" tabindex="-1">Super powered by GoMercato.it</a>' +
                ' 🚀</p>';

            FooterView.prototype.data = function () {
                return {
                    year: (new Date()).getFullYear(),
                };
            };

            applyFooterDom();
        });

    /**
     * Email-aware export on every list (Account, Contact, Task, …).
     * Without this, native Export + xlsx-email in formatList downloaded locally.
     */
    Espo.loader.requirePromise('views/record/list')
        .then(ListView => {
            return Espo.loader.requirePromise('nonprofit-espocrm:lib/reporting-list-export')
                .then(ReportingListExport => {
                    const proto = ListView.prototype;

                    proto.exportModalView = ReportingListExport.exportModalView;
                    proto.openReportingEmailExport = ReportingListExport.openReportingEmailExport;
                    proto.postReportingEmailExport = ReportingListExport.postReportingEmailExport;
                    proto.export = ReportingListExport.export;
                });
        });
})();
