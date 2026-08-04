define('nonprofit-espocrm:views/activity-offer/record/edit', [
    'views/record/edit',
    'nonprofit-espocrm:views/activity-offer/record/place-description-layout',
], function (Dep, PlaceDescriptionLayout) {

    return Dep.extend(Object.assign({}, PlaceDescriptionLayout, {

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupPlaceDescriptionLayout();
            this.setupAutoWeekName();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.afterRenderPlaceDescriptionLayout();
        },

        setupAutoWeekName: function () {
            this.listenTo(this.model, 'change:weekStart', () => {
                this.applyAutoWeekName();
            });

            if (this.model.get('weekStart')) {
                this.applyAutoWeekName();
            }
        },

        applyAutoWeekName: function () {
            const weekStart = this.model.get('weekStart');

            if (!weekStart) {
                return;
            }

            const start = this.getDateTime().toMoment(weekStart + ' 00:00:00');

            if (!start.isValid()) {
                return;
            }

            const end = start.clone().add(6, 'days');
            const name = start.format('DD.MM.YYYY') + ' - ' + end.format('DD.MM.YYYY')
                + ' turni per volontari';

            this.model.set('name', name);
        },
    }));
});
