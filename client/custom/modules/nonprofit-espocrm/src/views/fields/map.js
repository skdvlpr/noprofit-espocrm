/***
 * Map field: treat street-only addresses as displayable.
 * Core hasAddress() requires city or postalCode only.
 */
define('nonprofit-espocrm:views/fields/map', [
    'views/fields/map',
], function (Dep) {

    return Dep.extend({

        hasAddress() {
            if (Dep.prototype.hasAddress.call(this)) {
                return true;
            }

            return !!this.model.get(this.addressField + 'Street');
        },
    });
});
