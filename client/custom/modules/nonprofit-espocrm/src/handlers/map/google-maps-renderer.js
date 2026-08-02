/***
 * Google Maps renderer with reliable pin + street-level zoom.
 *
 * Must export an ES class (loader does `new Renderer(view)`). Backbone Dep.extend
 * breaks instantiation and leaves the View on Map modal blank.
 */
define('nonprofit-espocrm:handlers/map/google-maps-renderer', [
    'exports',
    'handlers/map/renderer',
], function (_exports, _renderer) {
    'use strict';

    Object.defineProperty(_exports, '__esModule', {value: true});
    _exports.default = void 0;

    const Base = _renderer && (_renderer.default || _renderer);

    /** @type {Promise<void>|null} */
    let mapsReadyPromise = null;

    const ensureMapsApi = (apiKey) => {
        const maps = window.google && window.google.maps;

        if (maps && typeof maps.Map === 'function' && typeof maps.Geocoder === 'function') {
            if (maps.importLibrary) {
                return maps.importLibrary('marker').catch(() => undefined).then(() => undefined);
            }

            return Promise.resolve();
        }

        if (mapsReadyPromise) {
            return mapsReadyPromise;
        }

        mapsReadyPromise = new Promise((resolve, reject) => {
            const callbackName = '__nonprofitEspocrmMapRendererLoaded';

            window[callbackName] = () => {
                delete window[callbackName];
                resolve();
            };

            const script = document.createElement('script');
            script.async = true;
            script.defer = true;
            script.onerror = () => {
                mapsReadyPromise = null;
                reject(new Error('Google Maps script failed to load'));
            };

            let src = 'https://maps.googleapis.com/maps/api/js?'
                + 'callback=' + callbackName
                + '&loading=async&v=weekly&libraries=marker,places';

            if (apiKey) {
                src += '&key=' + encodeURIComponent(apiKey);
            }

            script.src = src;
            document.head.appendChild(script);
        });

        return mapsReadyPromise;
    };

    const buildAddressString = (addressData) => {
        const parts = [];

        if (addressData.street) {
            parts.push(String(addressData.street).trim());
        }

        const cityLine = [addressData.postalCode, addressData.city]
            .filter(Boolean)
            .map(item => String(item).trim())
            .join(' ');

        if (cityLine) {
            parts.push(cityLine);
        }

        if (addressData.state) {
            parts.push(String(addressData.state).trim());
        }

        if (addressData.country) {
            parts.push(String(addressData.country).trim());
        }

        return parts.filter(Boolean).join(', ');
    };

    class GoogleMapsRenderer extends Base {
        /**
         * @param {import('views/fields/map').default} view
         */
        constructor(view) {
            super(view);
        }

        /**
         * @param {object} addressData
         */
        render(addressData) {
            const apiKey = this.view.getConfig().get('googleMapsApiKey');

            ensureMapsApi(apiKey)
                .then(() => this.initMapGoogle(addressData))
                .catch(err => {
                    console.error('[map] Google Maps unavailable:', err && err.message ? err.message : err);
                });
        }

        /**
         * @param {object} addressData
         */
        initMapGoogle(addressData) {
            const mapEl = this.view.$el.find('.map').get(0);

            if (!mapEl) {
                console.error('[map] .map element not found');

                return;
            }

            // Modal with height:auto can collapse to 0 — force a usable viewport.
            if (!mapEl.style.minHeight) {
                mapEl.style.minHeight = '320px';
            }

            if (mapEl.clientHeight < 80) {
                mapEl.style.height = '60vh';
            }

            const geocoder = new google.maps.Geocoder();
            const mapId = this.view.getConfig().get('googleMapsMapId');
            let map;

            const mapOptions = {
                zoom: 15,
                center: {lat: 45.07, lng: 7.69},
                scrollwheel: false,
            };

            if (mapId) {
                mapOptions.mapId = mapId;
            }

            try {
                map = new google.maps.Map(mapEl, mapOptions);
            }
            catch (e) {
                console.warn('[map] Map init with mapId failed, retrying without:', e.message);

                try {
                    delete mapOptions.mapId;
                    map = new google.maps.Map(mapEl, mapOptions);
                }
                catch (e2) {
                    console.error('[map] Map init failed:', e2.message);

                    return;
                }
            }

            const address = buildAddressString(addressData);

            if (!address) {
                console.warn('[map] Empty address; cannot geocode');

                return;
            }

            geocoder.geocode({address: address}, (results, status) => {
                if (status === google.maps.GeocoderStatus.OK && results && results[0]) {
                    this.applyGeocodeResult(map, results[0]);

                    return;
                }

                console.warn('[map] Geocode failed:', status, address);
            });
        }

        /**
         * @param {google.maps.Map} map
         * @param {google.maps.GeocoderResult} result
         */
        applyGeocodeResult(map, result) {
            const geometry = result.geometry;

            if (!geometry || !geometry.location) {
                return;
            }

            map.setCenter(geometry.location);
            this.placeMarker(map, geometry.location);

            if (geometry.viewport && typeof map.fitBounds === 'function') {
                map.fitBounds(geometry.viewport);

                return;
            }

            const locationType = geometry.location_type || '';

            if (locationType === 'ROOFTOP' || locationType === 'RANGE_INTERPOLATED') {
                map.setZoom(17);
            }
            else if (locationType === 'GEOMETRIC_CENTER') {
                map.setZoom(16);
            }
            else {
                map.setZoom(15);
            }
        }

        /**
         * @param {google.maps.Map} map
         * @param {google.maps.LatLng} position
         */
        placeMarker(map, position) {
            try {
                if (
                    google.maps.marker &&
                    typeof google.maps.marker.AdvancedMarkerElement === 'function' &&
                    this.view.getConfig().get('googleMapsMapId')
                ) {
                    new google.maps.marker.AdvancedMarkerElement({
                        map: map,
                        position: position,
                    });

                    return;
                }
            }
            catch (e) {
                console.warn('[map] AdvancedMarkerElement failed, falling back:', e.message);
            }

            if (typeof google.maps.Marker === 'function') {
                new google.maps.Marker({
                    map: map,
                    position: position,
                });
            }
        }
    }

    _exports.default = GoogleMapsRenderer;
});
