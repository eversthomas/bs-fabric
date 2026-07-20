<?php

declare(strict_types=1);

namespace BsFabric\Geo;

/**
 * Nominatim-kompatibles Geocoding (OSM öffentlich oder eigener Server).
 *
 * @see https://operations.osmfoundation.org/policies/nominatim/
 */
final class NominatimGeocoder implements GeocoderInterface
{
    public function __construct(
        private readonly string $userAgent,
        private readonly string $endpoint,
    ) {
    }

    public function geocode(array $address): GeocodeResult
    {
        $query = $this->buildQuery($address);

        if ($query === '') {
            return GeocodeResult::failed(__('Adresse ist unvollständig für Geocoding.', 'bs-fabric'));
        }

        $url = add_query_arg(
            [
                'q' => $query,
                'format' => 'json',
                'limit' => 1,
                'addressdetails' => 0,
            ],
            $this->endpoint
        );

        $country = isset($address['country']) ? strtoupper(trim((string) $address['country'])) : '';

        if (strlen($country) === 2) {
            $url = add_query_arg('countrycodes', strtolower($country), $url);
        }

        $response = wp_remote_get($url, [
            'timeout' => 12,
            'headers' => [
                'User-Agent' => $this->userAgent,
                'Accept' => 'application/json',
            ],
        ]);

        if (is_wp_error($response)) {
            return GeocodeResult::failed(
                sprintf(
                    /* translators: %s: error message */
                    __('Geocoding fehlgeschlagen: %s', 'bs-fabric'),
                    $response->get_error_message()
                )
            );
        }

        $code = (int) wp_remote_retrieve_response_code($response);

        if ($code !== 200) {
            return GeocodeResult::failed(
                sprintf(
                    /* translators: %d: HTTP status */
                    __('Geocoding-Provider antwortete mit HTTP %d.', 'bs-fabric'),
                    $code
                )
            );
        }

        $body = wp_remote_retrieve_body($response);
        $decoded = json_decode($body, true);

        if (! is_array($decoded) || $decoded === []) {
            return GeocodeResult::failed(__('Keine Koordinaten für diese Adresse gefunden.', 'bs-fabric'));
        }

        $first = $decoded[0] ?? null;

        if (! is_array($first) || ! isset($first['lat'], $first['lon'])) {
            return GeocodeResult::failed(__('Keine Koordinaten für diese Adresse gefunden.', 'bs-fabric'));
        }

        if (! is_numeric($first['lat']) || ! is_numeric($first['lon'])) {
            return GeocodeResult::failed(__('Ungültige Koordinaten vom Geocoding-Provider.', 'bs-fabric'));
        }

        $lat = (float) $first['lat'];
        $lng = (float) $first['lon'];

        if (! is_finite($lat) || ! is_finite($lng)) {
            return GeocodeResult::failed(__('Ungültige Koordinaten vom Geocoding-Provider.', 'bs-fabric'));
        }

        return GeocodeResult::ok($lat, $lng);
    }

    /**
     * @param array{
     *     street?: string,
     *     zip?: string,
     *     city?: string,
     *     country?: string,
     *     formatted?: string
     * } $address
     */
    private function buildQuery(array $address): string
    {
        $segments = [];

        $street = trim((string) ($address['street'] ?? ''));
        $zip = trim((string) ($address['zip'] ?? ''));
        $city = trim((string) ($address['city'] ?? ''));

        if ($street !== '') {
            $segments[] = $street;
        }

        $cityLine = trim($zip . ' ' . $city);

        if ($cityLine !== '') {
            $segments[] = $cityLine;
        }

        $country = strtoupper(trim((string) ($address['country'] ?? '')));

        if (strlen($country) === 2) {
            $segments[] = $country;
        }

        if ($segments !== []) {
            return implode(', ', $segments);
        }

        return trim((string) ($address['formatted'] ?? ''));
    }
}
