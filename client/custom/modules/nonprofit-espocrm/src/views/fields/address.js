/***
 * Address field with Google Places Autocomplete on street input.
 *
 * Espo core only autocompletes city/state/country from admin lists.
 * This view loads Maps JS + Places library when googleMapsApiKey is set.
 */
define('nonprofit-espocrm:views/fields/address', [
    'views/fields/address',
], function (Dep) {

    /** @type {Promise<void>|null} */
    let placesLoaderPromise = null;

    const loadGooglePlaces = (apiKey) => {
        if (window.google && window.google.maps && window.google.maps.places) {
            return Promise.resolve();
        }

        if (placesLoaderPromise) {
            return placesLoaderPromise;
        }

        placesLoaderPromise = new Promise((resolve, reject) => {
            const callbackName = '__nonprofitEspocrmPlacesLoaded';

            window[callbackName] = () => {
                delete window[callbackName];
                resolve();
            };

            const script = document.createElement('script');
            script.async = true;
            script.defer = true;
            script.onerror = () => {
                placesLoaderPromise = null;
                reject(new Error('Google Maps Places script failed to load'));
            };
            script.src = 'https://maps.googleapis.com/maps/api/js?'
                + 'key=' + encodeURIComponent(apiKey)
                + '&libraries=places'
                + '&callback=' + callbackName
                + '&loading=async';

            document.head.appendChild(script);
        });

        return placesLoaderPromise;
    };

    const componentValue = (components, type, useShort = false) => {
        const part = components.find(item => item.types && item.types.includes(type));

        if (!part) {
            return '';
        }

        return useShort ? part.short_name : part.long_name;
    };

    return Dep.extend({

        afterRender() {
            Dep.prototype.afterRender.call(this);

            if (this.mode !== this.MODE_EDIT) {
                return;
            }

            this.initGooglePlacesAutocomplete();
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
                fields: ['address_components'],
                types: ['address'],
            });

            autocomplete.addListener('place_changed', () => {
                const place = autocomplete.getPlace();

                if (!place || !place.address_components) {
                    return;
                }

                this.fillAddressFromComponents(place.address_components);
                this.trigger('change');
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

            if (street) {
                this.$street.val(street);
            }

            const city = componentValue(components, 'locality')
                || componentValue(components, 'postal_town')
                || componentValue(components, 'administrative_area_level_3');
            const state = componentValue(components, 'administrative_area_level_1', true);
            const postal = componentValue(components, 'postal_code');
            const country = componentValue(components, 'country');

            if (city && this.$city) {
                this.$city.val(city);
            }

            if (state && this.$state) {
                this.$state.val(state);
            }

            if (postal && this.$postalCode) {
                this.$postalCode.val(postal);
            }

            if (country && this.$country) {
                this.$country.val(country);
            }

            this.controlStreetTextareaHeight();
        },
    });
});
