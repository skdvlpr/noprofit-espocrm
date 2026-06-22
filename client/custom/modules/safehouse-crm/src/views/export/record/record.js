define('safehouse-crm:views/export/record/record', [
    'views/export/record/record',
], function (Dep) {

    return Dep.extend({

        setupBeforeFinal() {
            Dep.prototype.setupBeforeFinal.call(this);
            this.setupEmailFormatTranslations();
        },

        setupEmailFormatTranslations() {
            const translatedOptions = {};

            (this.formatList || []).forEach(format => {
                const label = this.translate(format, 'options', 'Export');

                translatedOptions[format] = label === format ? format : label;
            });

            const fieldDefs = this.model.defs.fields.format || {};

            fieldDefs.translatedOptions = translatedOptions;
            this.model.defs.fields.format = fieldDefs;
        },
    });
});
