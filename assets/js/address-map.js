/**
 * Admin map picker for address fields (Leaflet + OSM tiles).
 */
(function () {
    'use strict';

    var DEFAULT_CENTER = [51.1657, 10.4515];
    var DEFAULT_ZOOM = 6;
    var PIN_ZOOM = 15;
    var markerIcon = null;

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.L === 'undefined') {
            return;
        }

        markerIcon = createMarkerIcon();

        document.querySelectorAll('.bs-fabric-field-address[data-map-picker="1"]').forEach(function (field) {
            initAddressMap(field);
        });
    });

    function createMarkerIcon() {
        var cfg = window.bsFabricAddressMap || {};

        if (!cfg.iconUrl || typeof L.icon !== 'function') {
            return undefined;
        }

        // Eigenes Icon mit absoluten URLs — Icon.Default hängt sonst imagePath davor (Leaflet 1.9).
        return L.icon({
            iconUrl: cfg.iconUrl,
            iconRetinaUrl: cfg.iconRetinaUrl || cfg.iconUrl,
            shadowUrl: cfg.shadowUrl || '',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            tooltipAnchor: [16, -28],
            shadowSize: [41, 41],
        });
    }

    function markerOptions() {
        var options = { draggable: true };

        if (markerIcon) {
            options.icon = markerIcon;
        }

        return options;
    }

    function initAddressMap(field) {
        var mapEl = field.querySelector('.bs-fabric-field-address-map');
        var latInput = field.querySelector('input[data-geo-key="lat"]');
        var lngInput = field.querySelector('input[data-geo-key="lng"]');
        var statusInput = field.querySelector('input[data-geo-key="geocode_status"]');
        var hashInput = field.querySelector('input[data-geo-key="geocode_hash"]');
        var coordsEl = field.querySelector('.bs-fabric-field-address-map-coords');

        if (!mapEl || !latInput || !lngInput) {
            return;
        }

        var lat = parseCoord(latInput.value);
        var lng = parseCoord(lngInput.value);
        var hasPin = lat !== null && lng !== null;
        var map = L.map(mapEl, {
            scrollWheelZoom: false,
        }).setView(hasPin ? [lat, lng] : DEFAULT_CENTER, hasPin ? PIN_ZOOM : DEFAULT_ZOOM);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        var marker = null;

        if (hasPin) {
            marker = L.marker([lat, lng], markerOptions()).addTo(map);
            bindMarker(marker);
        }

        map.on('click', function (event) {
            if (marker) {
                marker.setLatLng(event.latlng);
            } else {
                marker = L.marker(event.latlng, markerOptions()).addTo(map);
                bindMarker(marker);
            }

            persistCoords(event.latlng.lat, event.latlng.lng);
        });

        // Leaflet needs a refresh when the admin layout settles.
        window.setTimeout(function () {
            map.invalidateSize();
        }, 100);

        function bindMarker(currentMarker) {
            currentMarker.on('dragend', function () {
                var position = currentMarker.getLatLng();
                persistCoords(position.lat, position.lng);
            });
        }

        function persistCoords(nextLat, nextLng) {
            var roundedLat = roundCoord(nextLat);
            var roundedLng = roundCoord(nextLng);

            latInput.value = String(roundedLat);
            lngInput.value = String(roundedLng);

            if (statusInput) {
                statusInput.value = 'manual';
            }

            updateFingerprintHash().then(function () {
                updateCoordsLabel(roundedLat, roundedLng);
            });
        }

        function updateCoordsLabel(nextLat, nextLng) {
            if (!coordsEl) {
                return;
            }

            coordsEl.textContent = formatCoordsLabel(nextLat, nextLng);
            coordsEl.classList.remove('is-pending', 'is-failed');
            coordsEl.classList.add('is-manual');
        }

        function updateFingerprintHash() {
            if (!hashInput || !window.crypto || !window.crypto.subtle) {
                return Promise.resolve();
            }

            var parts = readAddressParts(field);
            var payload = [parts.street, parts.zip, parts.city, parts.country].join('|');

            return window.crypto.subtle
                .digest('SHA-256', new TextEncoder().encode(payload))
                .then(function (buffer) {
                    hashInput.value = hexFromBuffer(buffer);
                })
                .catch(function () {
                    // Fingerprint optional — Geocoding may overwrite on next save.
                });
        }

        // Keep hash in sync when address text changes (so geocoding can re-run).
        field.querySelectorAll('input[type="text"], select').forEach(function (input) {
            if (input.getAttribute('data-geo-key')) {
                return;
            }

            input.addEventListener('change', function () {
                if (hashInput) {
                    hashInput.value = '';
                }
            });
        });
    }

    function readAddressParts(field) {
        var street = field.querySelector('.bs-fabric-field-address-street');
        var zip = field.querySelector('.bs-fabric-field-address-zip');
        var city = field.querySelector('.bs-fabric-field-address-city');
        var country = field.querySelector('.bs-fabric-field-address-select, input[name$="[country]"]');

        return {
            street: street ? street.value.trim() : '',
            zip: zip ? zip.value.trim() : '',
            city: city ? city.value.trim() : '',
            country: country ? String(country.value || '').trim().toUpperCase() : '',
        };
    }

    function parseCoord(value) {
        if (value === null || value === undefined || value === '') {
            return null;
        }

        var number = Number(value);

        return Number.isFinite(number) ? number : null;
    }

    function roundCoord(value) {
        return Math.round(value * 1e6) / 1e6;
    }

    function formatCoordsLabel(lat, lng) {
        var template = (window.bsFabricAddressMap && window.bsFabricAddressMap.coordsLabel) || 'Pin: %1$s, %2$s';

        return template.replace('%1$s', String(lat)).replace('%2$s', String(lng));
    }

    function hexFromBuffer(buffer) {
        return Array.from(new Uint8Array(buffer))
            .map(function (byte) {
                return byte.toString(16).padStart(2, '0');
            })
            .join('');
    }
})();
