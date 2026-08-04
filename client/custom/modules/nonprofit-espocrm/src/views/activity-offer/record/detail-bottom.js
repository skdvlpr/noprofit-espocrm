define('nonprofit-espocrm:views/activity-offer/record/detail-bottom', [
    'views/record/detail-bottom',
], function (Dep) {

    /**
     * Native Stream stays last (below coverage / shifts / personal tasks).
     */
    return Dep.extend({

        setupStreamPanel: function () {
            Dep.prototype.setupStreamPanel.call(this);

            (this.panelList || []).forEach(panel => {
                if (panel && panel.name === 'stream') {
                    panel.index = 100;
                    panel.order = 100;
                }
            });
        },
    });
});
