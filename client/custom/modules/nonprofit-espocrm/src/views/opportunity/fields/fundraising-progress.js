define('nonprofit-espocrm:views/opportunity/fields/fundraising-progress', [
    'views/fields/base',
], function (Dep) {

    return Dep.extend({

        detailTemplate: 'nonprofit-espocrm:opportunity/fields/fundraising-progress/detail',
        editTemplate: 'nonprofit-espocrm:opportunity/fields/fundraising-progress/detail',
        listTemplate: 'nonprofit-espocrm:opportunity/fields/fundraising-progress/detail',

        data() {
            const collected = Number(this.model.get('fundraisingCollectedAmount') ?? 0);
            const target = Number(this.model.get('fundraisingTargetAmount') ?? 0);
            const percent = Number(this.model.get('fundraisingProgressPercent') ?? 0);
            const currency = this.getConfig().get('defaultCurrency') || 'EUR';

            return {
                label: this.translate('fundraisingProgress', 'fields', 'Opportunity'),
                noTargetLabel: this.translate('fundraisingProgressNoTarget', 'fields', 'Opportunity'),
                collectedFormatted: this.formatCurrency(collected, currency),
                targetFormatted: this.formatCurrency(target, currency),
                percent: percent,
                hasTarget: target > 0,
            };
        },

        formatCurrency(value, currency) {
            const config = this.getConfig();
            const symbol = this.getMetadata().get(['app', 'currency', 'symbolMap', currency]) || currency;
            const decimalPlaces = config.get('currencyDecimalPlaces');
            const thousandSeparator = config.get('thousandSeparator') || ',';
            const decimalMark = config.get('decimalMark') || '.';
            const format = config.get('currencyFormat') || 2;

            let num = Number(value);

            if (Number.isNaN(num)) {
                num = 0;
            }

            const parts = num.toFixed(decimalPlaces ?? 2).split('.');
            parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, thousandSeparator);
            const formatted = parts.join(decimalMark);

            if (format === 1) {
                return formatted + ' ' + currency;
            }

            if (format === 3) {
                return formatted + ' ' + symbol;
            }

            return symbol + formatted;
        },
    });
});
