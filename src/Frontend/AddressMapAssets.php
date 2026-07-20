<?php

declare(strict_types=1);

namespace BsFabric\Frontend;

/**
 * Frontend-Assets für read-only Address-Karten (Leaflet + OSM).
 */
final class AddressMapAssets
{
    private static bool $enqueued = false;

    public static function enqueue(): void
    {
        if (self::$enqueued || is_admin()) {
            return;
        }

        self::$enqueued = true;

        wp_enqueue_style(
            'bs-fabric-leaflet',
            BS_FABRIC_URL . 'assets/vendor/leaflet/leaflet.css',
            [],
            '1.9.4'
        );

        wp_enqueue_style(
            'bs-fabric-frontend-address',
            BS_FABRIC_URL . 'assets/css/bs-fabric-frontend-address.css',
            ['bs-fabric-leaflet'],
            BS_FABRIC_VERSION
        );

        wp_enqueue_script(
            'bs-fabric-leaflet',
            BS_FABRIC_URL . 'assets/vendor/leaflet/leaflet.js',
            [],
            '1.9.4',
            true
        );

        wp_enqueue_script(
            'bs-fabric-address-map-frontend',
            BS_FABRIC_URL . 'assets/js/address-map-frontend.js',
            ['bs-fabric-leaflet'],
            BS_FABRIC_VERSION,
            true
        );

        wp_localize_script(
            'bs-fabric-address-map-frontend',
            'bsFabricAddressMapFrontend',
            [
                'iconUrl' => BS_FABRIC_URL . 'assets/vendor/leaflet/images/marker-icon.png',
                'iconRetinaUrl' => BS_FABRIC_URL . 'assets/vendor/leaflet/images/marker-icon-2x.png',
                'shadowUrl' => BS_FABRIC_URL . 'assets/vendor/leaflet/images/marker-shadow.png',
            ]
        );
    }

    public static function resetForTests(): void
    {
        self::$enqueued = false;
    }
}
