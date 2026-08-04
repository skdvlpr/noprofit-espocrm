define('nonprofit-espocrm:views/activity-offer/record/detail', [
    'views/record/detail',
    'nonprofit-espocrm:views/activity-offer/record/place-description-layout',
], function (Dep, PlaceDescriptionLayout) {

    return Dep.extend(Object.assign({}, PlaceDescriptionLayout, {

        bottomView: 'nonprofit-espocrm:views/activity-offer/record/detail-bottom',

        setup: function () {
            Dep.prototype.setup.call(this);
            this.setupPlaceDescriptionLayout();
        },

        afterRender: function () {
            Dep.prototype.afterRender.call(this);
            this.afterRenderPlaceDescriptionLayout();
        },
    }));
});
