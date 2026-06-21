define('safehouse-crm:views/modals/meal-count-email-export', [
    'safehouse-crm:views/modals/reporting-email-export',
], function (Dep) {

    return Dep.extend({

        setup() {
            this.options.entityType = this.options.entityType || 'MealCount';
            Dep.prototype.setup.call(this);
        },
    });
});
