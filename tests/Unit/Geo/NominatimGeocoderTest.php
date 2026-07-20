<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Geo;

use BsFabric\Geo\GeocodeResult;
use BsFabric\Geo\NominatimGeocoder;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey\Functions;

final class NominatimGeocoderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Functions\when('__')->alias(static fn (string $text): string => $text);
        Functions\when('add_query_arg')->alias(static function (...$args): string {
            if (count($args) === 2 && is_array($args[0]) && is_string($args[1])) {
                return $args[1] . (str_contains($args[1], '?') ? '&' : '?') . http_build_query($args[0]);
            }

            if (count($args) === 3 && is_string($args[0]) && is_string($args[2])) {
                $sep = str_contains($args[2], '?') ? '&' : '?';

                return $args[2] . $sep . rawurlencode($args[0]) . '=' . rawurlencode((string) $args[1]);
            }

            return is_string($args[0] ?? null) ? $args[0] : '';
        });
    }

    public function testGeocodeReturnsCoordinates(): void
    {
        Functions\when('wp_remote_get')->alias(static function () {
            return [
                'response' => ['code' => 200],
                'body' => '[{"lat":"52.520008","lon":"13.404954"}]',
            ];
        });
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->alias(
            static fn (array $response): string => (string) $response['body']
        );

        $geocoder = new NominatimGeocoder('bs-fabric-test/1.0', 'https://nominatim.openstreetmap.org/search');
        $result = $geocoder->geocode([
            'street' => 'Musterstraße 1',
            'zip' => '10115',
            'city' => 'Berlin',
            'country' => 'DE',
        ]);

        $this->assertTrue($result->isOk());
        $this->assertSame(52.520008, $result->lat);
        $this->assertSame(13.404954, $result->lng);
    }

    public function testGeocodeReturnsFailedOnEmptyResults(): void
    {
        Functions\when('wp_remote_get')->justReturn([
            'response' => ['code' => 200],
            'body' => '[]',
        ]);
        Functions\when('is_wp_error')->justReturn(false);
        Functions\when('wp_remote_retrieve_response_code')->justReturn(200);
        Functions\when('wp_remote_retrieve_body')->justReturn('[]');

        $geocoder = new NominatimGeocoder('bs-fabric-test/1.0', 'https://nominatim.openstreetmap.org/search');
        $result = $geocoder->geocode([
            'street' => 'Nirgendwo 1',
            'zip' => '00000',
            'city' => 'Nixburg',
            'country' => 'DE',
        ]);

        $this->assertFalse($result->isOk());
        $this->assertSame(GeocodeResult::STATUS_FAILED, $result->status);
    }

    public function testGeocodeReturnsFailedOnWpError(): void
    {
        $error = new class {
            public function get_error_message(): string
            {
                return 'timeout';
            }
        };

        Functions\when('wp_remote_get')->justReturn($error);
        Functions\when('is_wp_error')->justReturn(true);

        $geocoder = new NominatimGeocoder('bs-fabric-test/1.0', 'https://nominatim.example.test/search');
        $result = $geocoder->geocode([
            'city' => 'Berlin',
            'country' => 'DE',
        ]);

        $this->assertFalse($result->isOk());
        $this->assertStringContainsString('timeout', $result->message);
    }
}
