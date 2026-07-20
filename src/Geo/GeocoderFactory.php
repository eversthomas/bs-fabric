<?php

declare(strict_types=1);

namespace BsFabric\Geo;

use BsFabric\Core\PluginSettings;

final class GeocoderFactory
{
    public function create(?string $provider = null): ?GeocoderInterface
    {
        $provider ??= PluginSettings::geocodingProvider();

        return match ($provider) {
            PluginSettings::GEOCODER_NOMINATIM => new NominatimGeocoder(
                PluginSettings::geocodingUserAgent(),
                PluginSettings::geocodingNominatimEndpoint()
            ),
            default => null,
        };
    }
}
