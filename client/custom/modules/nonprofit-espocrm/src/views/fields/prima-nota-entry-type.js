define('nonprofit-espocrm:views/fields/prima-nota-entry-type', [
    'views/fields/enum',
], function (Dep) {

    return Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:' + this.name, () => {
                this.applyEntryTypeStyle();
            });
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);
            this.applyEntryTypeStyle();
        },

        applyEntryTypeStyle() {
            const value = this.model.get(this.name) || '';
            const incomeClass = 'prima-nota-entry-type-income';
            const expenseClass = 'prima-nota-entry-type-expense';

            this.$el.removeClass(incomeClass + ' ' + expenseClass);

            const $select = this.$el.find('select');

            $select.removeClass(incomeClass + ' ' + expenseClass);

            if (value === 'Income') {
                this.$el.addClass(incomeClass);
                $select.addClass(incomeClass);
            } else if (value === 'Expense') {
                this.$el.addClass(expenseClass);
                $select.addClass(expenseClass);
            }

            $select.off('change.primaNotaEntryType').on('change.primaNotaEntryType', () => {
                this.applyEntryTypeStyle();
            });
        },
    });
});
