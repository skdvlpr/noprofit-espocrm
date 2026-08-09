/***
 * Address field with Google Places Autocomplete on street input,
 * and View on Map available in edit/mini create as well as detail.
 *
 * Espo core only autocompletes city/state/country from admin lists,
 * and only renders "View on Map" in read/detail templates when city
 * or postal code is set.
 */
define('nonprofit-espocrm:views/fields/address', [
    'views/fields/address',
], function (Dep) {

    const loadGooglePlaces = (apiKey) => {
        if (window.SafehouseGooglePlaces && typeof window.SafehouseGooglePlaces.ensure === 'function') {
            return window.SafehouseGooglePlaces.ensure(apiKey);
        }

        if (window.google && window.google.maps && window.google.maps.places
            && typeof window.google.maps.places.Autocomplete === 'function'
        ) {
            return Promise.resolve();
        }

        if (window.google && window.google.maps && window.google.maps.importLibrary) {
            return window.google.maps.importLibrary('places');
        }

        return Promise.reject(new Error('SafehouseGooglePlaces loader missing'));
    };

    const componentValue = (components, type, useShort = false) => {
        const part = components.find(item => item.types && item.types.includes(type));

        if (!part) {
            return '';
        }

        return useShort ? part.short_name : part.long_name;
    };

    return Dep.extend({

        // Explicit multiline lines (street/city/…) — core breaklines + sparse
        // list clones sometimes left only city visible in Aurora drawers.
        detailTemplate: 'nonprofit-espocrm:fields/address/detail',
        listTemplate: 'nonprofit-espocrm:fields/address/detail',

        data() {
            const data = Dep.prototype.data.call(this);

            if (this.isReadMode()) {
                data.addressLines = this.getAddressLines();

                if (data.addressLines.length) {
                    data.formattedAddress = data.addressLines.join('\n');
                    data.isNone = false;
                    data.isLoading = false;
                }
            }

            if (this.params.viewMap && this.canBeDisplayedOnMap()) {
                data.viewMap = true;
                data.viewMapLink = '#AddressMap/view/'
                    + this.model.entityType + '/'
                    + (this.model.id || '0') + '/'
                    + this.name;
            }

            return data;
        },

        /**
         * Ordered non-empty address parts for detail/list display.
         * @return {string[]}
         */
        getAddressLines() {
            const street = (this.model.get(this.name + 'Street') || '').toString().trim();
            const city = (this.model.get(this.name + 'City') || '').toString().trim();
            const state = (this.model.get(this.name + 'State') || '').toString().trim();
            const postal = (this.model.get(this.name + 'PostalCode') || '').toString().trim();
            const country = (this.model.get(this.name + 'Country') || '').toString().trim();

            const lines = [];

            if (street) {
                lines.push(street);
            }

            const cityLine = [city, state].filter(Boolean).join(', ');
            const cityPostal = [cityLine, postal].filter(Boolean).join(' ').trim();

            if (cityPostal) {
                lines.push(cityPostal);
            }

            if (country) {
                lines.push(country);
            }

            return lines;
        },

        /**
         * Core requires city or postalCode. Also allow street so Italian
         * addresses typed without a Places pick still unlock the map link.
         */
        canBeDisplayedOnMap() {
            if (Dep.prototype.canBeDisplayedOnMap.call(this)) {
                return true;
            }

            if (this.model.get(this.name + 'Street')) {
                return true;
            }

            if (this.isEditMode() && this.$street && this.$street.length) {
                return !!(this.$street.val() || '').trim();
            }

            return false;
        },

        afterRender() {
            Dep.prototype.afterRender.call(this);

            this.ensureViewMapLink();

            if (!this.isEditMode()) {
                return;
            }

            this.initGooglePlacesAutocomplete();

            this.$el
                .off('input.nonprofitViewMap change.nonprofitViewMap')
                .on(
                    'input.nonprofitViewMap change.nonprofitViewMap',
                    'input, textarea',
                    () => this.ensureViewMapLink()
                );
        },

        /**
         * Edit templates omit {{#if viewMap}}; inject the same link used in detail.
         */
        ensureViewMapLink() {
            if (!this.params.viewMap) {
                return;
            }

            if (this.isEditMode()) {
                this.syncAddressFromDomToModel(true);
            }

            const $existing = this.$el.find('[data-action="viewMap"]');

            if (!this.canBeDisplayedOnMap()) {
                $existing.closest('.address-view-map-link').remove();
                $existing.remove();

                return;
            }

            if ($existing.length) {
                return;
            }

            const $link = $('<div class="address-view-map-link">').append(
                $('<a>')
                    .attr('href', '#')
                    .attr('data-action', 'viewMap')
                    .addClass('small')
                    .css('user-select', 'none')
                    .text(this.translate('View on Map'))
            );

            this.$el.append($link);
        },

        syncAddressFromDomToModel(silent) {
            if (!this.isEditMode()) {
                return;
            }

            const attrs = {};

            (this.addressPartList || []).forEach(item => {
                const $input = this['$' + item];

                if (!$input || !$input.length) {
                    return;
                }

                const attribute = this.subFieldMap[item];
                const value = ($input.val() || '').trim() || null;

                attrs[attribute] = value;
            });

            if (Object.keys(attrs).length) {
                this.model.set(attrs, {silent: !!silent});
            }
        },

        async viewMapAction() {
            if (this.isEditMode()) {
                this.syncAddressFromDomToModel(false);
            }

            await Dep.prototype.viewMapAction.call(this);
        },

        initGooglePlacesAutocomplete() {
            const apiKey = this.getConfig().get('googleMapsApiKey');

            if (!apiKey || !this.$street || !this.$street.length) {
                return;
            }

            loadGooglePlaces(apiKey)
                .then(() => this.setupPlacesAutocomplete())
                .catch(err => console.warn('[address] Google Places unavailable:', err.message));
        },

        setupPlacesAutocomplete() {
            const input = this.$street.get(0);

            if (!input || !window.google || !google.maps || !google.maps.places) {
                return;
            }

            const autocomplete = new google.maps.places.Autocomplete(input, {
                fields: ['address_components', 'formatted_address'],
                // No types filter: allow streets and localities (e.g. city names).
            });

            const bump = () => {
                if (window.SafehouseGooglePlaces && window.SafehouseGooglePlaces.bumpPacZIndex) {
                    window.SafehouseGooglePlaces.bumpPacZIndex();
                }
                else {
                    document.querySelectorAll('.pac-container').forEach(el => {
                        el.style.zIndex = '30000';
                    });
                }
            };

            input.addEventListener('focus', () => {
                setTimeout(bump, 0);
                setTimeout(bump, 100);
            });
            input.addEventListener('input', () => setTimeout(bump, 0));

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();

                if (!place || !place.address_components) {
                    return;
                }

                this.fillAddressFromComponents(place.address_components);
                this.trigger('change');
                this.ensureViewMapLink();
            });

            this.once('remove', () => {
                if (google.maps.event) {
                    google.maps.event.clearInstanceListeners(autocomplete);
                }
            });
        },

        fillAddressFromComponents(components) {
            const route = componentValue(components, 'route');
            const streetNumber = componentValue(components, 'street_number');
            const street = [route, streetNumber].filter(Boolean).join(' ');

            const city = componentValue(components, 'locality')
                || componentValue(components, 'postal_town')
                || componentValue(components, 'administrative_area_level_3')
                || componentValue(components, 'administrative_area_level_2');
            const state = componentValue(components, 'administrative_area_level_1', true)
                || componentValue(components, 'administrative_area_level_1');
            const postal = componentValue(components, 'postal_code');
            const country = componentValue(components, 'country');

            const attrs = {};

            if (street && this.$street) {
                this.$street.val(street);
                attrs[this.name + 'Street'] = street;
            }

            if (city && this.$city) {
                this.$city.val(city);
                attrs[this.name + 'City'] = city;
            }

            if (state && this.$state) {
                this.$state.val(state);
                attrs[this.name + 'State'] = state;
            }

            if (postal && this.$postalCode) {
                this.$postalCode.val(postal);
                attrs[this.name + 'PostalCode'] = postal;
            }

            if (country && this.$country) {
                this.$country.val(country);
                attrs[this.name + 'Country'] = country;
            }

            if (Object.keys(attrs).length) {
                this.model.set(attrs);
            }

            this.controlStreetTextareaHeight();
        },
    });
});
