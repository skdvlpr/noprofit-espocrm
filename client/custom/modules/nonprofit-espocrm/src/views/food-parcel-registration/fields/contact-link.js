define('nonprofit-espocrm:views/food-parcel-registration/fields/contact-link', [
    'views/fields/link',
], function (Dep) {

    return Dep.extend({

        /**
         * Defer applying the selection until after the select-records modal
         * finishes closing. Immediate model/field updates during onSelect can
         * prevent Espo.Ui.Dialog.close() from running.
         */
        async actionSelect() {
            const createView = this.createView.bind(this);

            this.createView = (key, viewName, options, callback) => {
                if (key === 'modal' && options && typeof options.onSelect === 'function') {
                    const onSelect = options.onSelect;

                    options.onSelect = list => {
                        setTimeout(() => onSelect(list), 0);
                    };
                }

                return createView(key, viewName, options, callback);
            };

            try {
                await Dep.prototype.actionSelect.call(this);
            } finally {
                this.createView = createView;
            }
        },
    });
});
