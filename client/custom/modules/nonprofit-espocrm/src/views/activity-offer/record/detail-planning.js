define('nonprofit-espocrm:views/activity-offer/record/detail-planning', [
    'views/record/detail-bottom',
], function (Dep) {

    /**
     * Full-width planning band above Overview.
     * Must mirror DetailBottom panel chrome (actionsViewKey / alterPanels /
     * setupPanelsFinal) — skipping those renders literal "undefined" in the
     * panel header actions slot and can break the parent detail wait chain.
     */
    return Dep.extend({

        name: 'planning',
        streamPanel: false,
        relationshipPanels: false,

        setupPanels: function () {
            const scope = this.scope;

            this.panelList = this.getMetadata()
                .get(['clientDefs', scope, 'planningPanels', this.type]) || [];

            this.panelList = [...this.panelList].map(item => {
                if (item && item.reference) {
                    return {
                        ...this.getMetadata().get(`app.clientRecord.panels.${item.reference}`),
                        ...item,
                    };
                }

                return item;
            });

            this.panelList.forEach(item => {
                if (!item || 'index' in item) {
                    return;
                }

                if ('order' in item) {
                    item.index = item.order;
                }
            });
        },

        setup: function () {
            this.type = this.mode;

            if ('type' in this.options) {
                this.type = this.options.type;
            }

            this.panelList = [];
            this.setupInitial();
            this.setupPanels();

            this.panelList = this.panelList.filter(p => {
                if (!p || !p.name) {
                    return false;
                }

                if (p.aclScope && !this.getAcl().checkScope(p.aclScope)) {
                    return false;
                }

                if (
                    p.accessDataList &&
                    !Espo.Utils.checkAccessDataList(
                        p.accessDataList,
                        this.getAcl(),
                        this.getUser()
                    )
                ) {
                    return false;
                }

                return true;
            });

            this.panelList = this.panelList.map((item, i) => {
                item = Espo.Utils.cloneDeep(item);

                const relDefs = this.getMetadata()
                    .get(['clientDefs', this.scope, 'relationshipPanels', item.name]);

                if (relDefs && typeof relDefs === 'object') {
                    for (const key in relDefs) {
                        if (!(key in item)) {
                            item[key] = Espo.Utils.cloneDeep(relDefs[key]);
                        }
                    }
                }

                item.order = ('order' in item) ? item.order : i;
                item.index = ('index' in item) ? item.index : item.order;

                if (this.recordHelper.getPanelStateParam(item.name, 'hidden') !== null) {
                    item.hidden = this.recordHelper.getPanelStateParam(item.name, 'hidden');
                }
                else {
                    this.recordHelper.setPanelStateParam(
                        item.name,
                        'hidden',
                        item.hidden || false
                    );
                }

                // Required by record/bottom.tpl {{{var actionsViewKey}}}
                item.actionsViewKey = item.name + 'Actions';

                return item;
            });

            this.panelList.sort((a, b) => a.index - b.index);

            this.alterPanels();
            this.setupPanelsFinal();
            this.setupPanelViews();
        },
    });
});
