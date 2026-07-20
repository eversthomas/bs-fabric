/**
 * Read-only frontend maps for address fields.
 */
(function () {
    'use strict';

    var DEFAULT_ZOOM = 15;
    var markerIcon = null;

    document.addEventListener('DOMContentLoaded', function () {
        if (typeof window.L === 'undefined') {
            return;
        }

        markerIcon = createMarkerIcon();

        document.querySelectorAll('.bs-fabric-address-map[data-lat][data-lng]').forEach(function (mapEl) {
            initMap(mapEl);
        });
    });

    function createMarkerIcon() {
        var cfg = window.bsFabricAddressMapFrontend || {};

        if (!cfg.iconUrl || typeof L.icon !== 'function') {
            return undefined;
        }

        return L.icon({
            iconUrl: cfg.iconUrl,
            iconRetinaUrl: cfg.iconRetinaUrl || cfg.iconUrl,
            shadowUrl: cfg.shadowUrl || '',
            iconSize: [25, 41],
            iconAnchor: [12, 41],
            popupAnchor: [1, -34],
            shadowSize: [41, 41],
        });
    }

    function initMap(mapEl) {
        var lat = Number(mapEl.getAttribute('data-lat'));
        var lng = Number(mapEl.getAttribute('data-lng'));

        if (!Number.isFinite(lat) || !Number.isFinite(lng)) {
            return;
        }

        var zoomAttr = mapEl.getAttribute('data-zoom');
        var zoom = zoomAttr ? Number(zoomAttr) : DEFAULT_ZOOM;

        if (!Number.isFinite(zoom)) {
            zoom = DEFAULT_ZOOM;
        }

        var map = L.map(mapEl, {
            scrollWheelZoom: false,
            dragging: !window.matchMedia('(pointer: coarse)').matches,
        }).setView([lat, lng], zoom);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a>',
            maxZoom: 19,
        }).addTo(map);

        var markerOptions = {};

        if (markerIcon) {
            markerOptions.icon = markerIcon;
        }

        L.marker([lat, lng], markerOptions).addTo(map);

        window.setTimeout(function () {
            map.invalidateSize();
        }, 100);
    }
})();
