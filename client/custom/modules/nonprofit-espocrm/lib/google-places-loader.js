/**
 * Single shared Google Maps Places loader for address + week-slots editors.
 * Avoids hanging when maps.js was already loaded without the places library.
 */
(function (global) {
    'use strict';

    let loadPromise = null;

    /**
     * @param {string} apiKey
     * @returns {Promise<void>}
     */
    function ensureGooglePlaces(apiKey) {
        if (!apiKey) {
            return Promise.reject(new Error('Missing googleMapsApiKey'));
        }

        const placesReady = () =>
            !!(global.google
                && global.google.maps
                && global.google.maps.places
                && typeof global.google.maps.places.Autocomplete === 'function');

        if (placesReady()) {
            return Promise.resolve();
        }

        if (global.google && global.google.maps && typeof global.google.maps.importLibrary === 'function') {
            return global.google.maps.importLibrary('places').then(() => {
                if (!placesReady()) {
                    throw new Error('Places library import did not expose Autocomplete');
                }
            });
        }

        if (loadPromise) {
            return loadPromise;
        }

        loadPromise = new Promise((resolve, reject) => {
            const callbackName = '__safehouseGooglePlacesLoaded';

            global[callbackName] = () => {
                delete global[callbackName];

                const finish = () => {
                    if (placesReady()) {
                        resolve();

                        return;
                    }

                    if (global.google && global.google.maps && global.google.maps.importLibrary) {
                        global.google.maps.importLibrary('places')
                            .then(() => {
                                if (!placesReady()) {
                                    throw new Error('Places Autocomplete unavailable after importLibrary');
                                }
                            })
                            .then(resolve)
                            .catch(err => {
                                loadPromise = null;
                                reject(err);
                            });

                        return;
                    }

                    loadPromise = null;
                    reject(new Error('Google Places Autocomplete unavailable'));
                };

                finish();
            };

            const script = document.createElement('script');
            script.async = true;
            script.defer = true;
            script.onerror = () => {
                loadPromise = null;
                reject(new Error('Google Maps Places script failed to load'));
            };
            script.src = 'https://maps.googleapis.com/maps/api/js?'
                + 'key=' + encodeURIComponent(apiKey)
                + '&libraries=places,marker'
                + '&callback=' + callbackName
                + '&loading=async'
                + '&v=weekly';

            document.head.appendChild(script);
        });

        return loadPromise;
    }

    /**
     * Keep .pac-container above Espo/Aurora modals.
     */
    function bumpPacZIndex() {
        document.querySelectorAll('.pac-container').forEach(el => {
            el.style.zIndex = '30000';
        });
    }

    global.SafehouseGooglePlaces = {
        ensure: ensureGooglePlaces,
        bumpPacZIndex: bumpPacZIndex,
    };
})(window);
