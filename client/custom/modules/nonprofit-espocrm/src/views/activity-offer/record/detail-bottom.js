define('nonprofit-espocrm:views/activity-offer/record/detail-bottom', [
    'views/record/detail-bottom',
], function (Dep) {

    /**
     * Bottom under Overview: Shifts + Personal tasks, then Stream last.
     * Coverage / volunteer match live in the full-width planning band above.
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
