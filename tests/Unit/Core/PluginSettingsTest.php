<?php

declare(strict_types=1);

namespace BsFabric\Tests\Unit\Core;

use BsFabric\Core\PluginSettings;
use BsFabric\Tests\Unit\TestCase;
use Brain\Monkey;
use Brain\Monkey\Functions;

final class PluginSettingsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Monkey\setUp();

        Functions\when('sanitize_key')->alias(static fn (string $value): string => strtolower(preg_replace('/[^a-z0-9_\-]/', '', $value) ?? ''));
        Functions\when('sanitize_text_field')->returnArg();
        Functions\when('esc_url_raw')->alias(static fn (string $url): string => $url);
        Functions\when('untrailingslashit')->alias(static fn (string $url): string => rtrim($url, '/'));
    }

    protected function tearDown(): void
    {
        Monkey\tearDown();
        parent::tearDown();
    }

    public function testDefaults(): void
    {
        Functions\when('get_option')->justReturn(false);

        self::assertSame('Fabric', PluginSettings::menuLabel());
        self::assertSame('draft', PluginSettings::defaultEntryStatus());
        self::assertTrue(PluginSettings::lockFieldKeys());
        self::assertFalse(PluginSettings::deleteOnUninstall());
        self::assertSame(PluginSettings::GEOCODER_NOMINATIM, PluginSettings::geocodingProvider());
        self::assertSame(
            PluginSettings::DEFAULT_NOMINATIM_ENDPOINT,
            PluginSettings::geocodingNominatimEndpoint()
        );
    }

    public function testSaveAndReadCustomValues(): void
    {
        $stored = [];

        Functions\when('get_option')->alias(function (string $key, $default = false) use (&$stored) {
            return $stored[$key] ?? $default;
        });

        Functions\when('update_option')->alias(function (string $key, $value) use (&$stored): bool {
            $stored[$key] = $value;

            return true;
        });

        PluginSettings::save([
            PluginSettings::MENU_LABEL => 'Mein Fabric',
            PluginSettings::DEFAULT_ENTRY_STATUS => 'published',
            PluginSettings::LOCK_FIELD_KEYS => '',
            PluginSettings::DELETE_ON_UNINSTALL => '1',
            PluginSettings::GEOCODING_PROVIDER => PluginSettings::GEOCODER_NONE,
            PluginSettings::GEOCODING_NOMINATIM_ENDPOINT => 'https://nominatim.example.test/search/',
        ]);

        self::assertSame('Mein Fabric', PluginSettings::menuLabel());
        self::assertSame('published', PluginSettings::defaultEntryStatus());
        self::assertFalse(PluginSettings::lockFieldKeys());
        self::assertTrue(PluginSettings::deleteOnUninstall());
        self::assertSame(PluginSettings::GEOCODER_NONE, PluginSettings::geocodingProvider());
        self::assertSame('https://nominatim.example.test/search', PluginSettings::geocodingNominatimEndpoint());
    }

    public function testInvalidStatusFallsBackToDraft(): void
    {
        Functions\when('get_option')->justReturn([
            PluginSettings::DEFAULT_ENTRY_STATUS => 'invalid',
        ]);

        self::assertSame('draft', PluginSettings::defaultEntryStatus());
    }

    public function testInvalidGeocodingProviderFallsBackToNominatim(): void
    {
        Functions\when('get_option')->justReturn([
            PluginSettings::GEOCODING_PROVIDER => 'bing',
        ]);

        self::assertSame(PluginSettings::GEOCODER_NOMINATIM, PluginSettings::geocodingProvider());
    }

    public function testLegacyGoogleProviderMigratesToNominatim(): void
    {
        Functions\when('get_option')->justReturn([
            PluginSettings::GEOCODING_PROVIDER => 'google',
            'geocoding_api_key' => 'legacy-key',
        ]);

        self::assertSame(PluginSettings::GEOCODER_NOMINATIM, PluginSettings::geocodingProvider());
    }

    public function testGeocodingUserAgentIncludesVersionAndSite(): void
    {
        Functions\when('get_option')->alias(static function (string $key, $default = false) {
            if ($key === 'admin_email') {
                return 'admin@example.com';
            }

            return $default;
        });
        Functions\when('home_url')->justReturn('https://example.com/');
        Functions\when('sanitize_email')->alias(static fn (string $email): string => $email);

        $ua = PluginSettings::geocodingUserAgent();

        self::assertStringContainsString('bs-fabric/', $ua);
        self::assertStringContainsString('https://example.com/', $ua);
        self::assertStringContainsString('admin@example.com', $ua);
    }
}
