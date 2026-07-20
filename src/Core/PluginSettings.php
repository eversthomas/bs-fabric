<?php

declare(strict_types=1);

namespace BsFabric\Core;

final class PluginSettings
{
    public const OPTION_KEY = 'bs_fabric_plugin_settings';

    public const MENU_LABEL = 'menu_label';

    public const DEFAULT_ENTRY_STATUS = 'default_entry_status';

    public const LOCK_FIELD_KEYS = 'lock_field_keys';

    public const DELETE_ON_UNINSTALL = 'delete_on_uninstall';

    public const GEOCODING_PROVIDER = 'geocoding_provider';

    public const GEOCODING_NOMINATIM_ENDPOINT = 'geocoding_nominatim_endpoint';

    public const GEOCODER_NONE = 'none';

    public const GEOCODER_NOMINATIM = 'nominatim';

    public const DEFAULT_NOMINATIM_ENDPOINT = 'https://nominatim.openstreetmap.org/search';

    /** @var list<string> */
    public const ENTRY_STATUSES = ['draft', 'published', 'archived'];

    /** @var list<string> */
    public const GEOCODING_PROVIDERS = [
        self::GEOCODER_NONE,
        self::GEOCODER_NOMINATIM,
    ];

    /**
     * @return array<string, mixed>
     */
    public static function defaults(): array
    {
        return [
            self::MENU_LABEL => 'Fabric',
            self::DEFAULT_ENTRY_STATUS => 'draft',
            self::LOCK_FIELD_KEYS => true,
            self::DELETE_ON_UNINSTALL => false,
            self::GEOCODING_PROVIDER => self::GEOCODER_NOMINATIM,
            self::GEOCODING_NOMINATIM_ENDPOINT => self::DEFAULT_NOMINATIM_ENDPOINT,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function get(): array
    {
        $stored = get_option(self::OPTION_KEY, []);

        if (! is_array($stored)) {
            $stored = [];
        }

        // Legacy: Google-Provider / API-Key entfernen (nicht mehr unterstützt).
        unset($stored['geocoding_api_key']);

        if (($stored[self::GEOCODING_PROVIDER] ?? '') === 'google') {
            $stored[self::GEOCODING_PROVIDER] = self::GEOCODER_NOMINATIM;
        }

        return array_merge(self::defaults(), $stored);
    }

    /**
     * @param array<string, mixed> $input
     */
    public static function save(array $input): void
    {
        $settings = self::sanitize($input);
        update_option(self::OPTION_KEY, $settings, false);
    }

    public static function menuLabel(): string
    {
        $label = trim((string) (self::get()[self::MENU_LABEL] ?? ''));

        return $label !== '' ? $label : (string) self::defaults()[self::MENU_LABEL];
    }

    public static function defaultEntryStatus(): string
    {
        $status = sanitize_key((string) (self::get()[self::DEFAULT_ENTRY_STATUS] ?? 'draft'));

        return in_array($status, self::ENTRY_STATUSES, true) ? $status : 'draft';
    }

    public static function lockFieldKeys(): bool
    {
        return ! empty(self::get()[self::LOCK_FIELD_KEYS]);
    }

    public static function deleteOnUninstall(): bool
    {
        return ! empty(self::get()[self::DELETE_ON_UNINSTALL]);
    }

    public static function geocodingProvider(): string
    {
        $provider = sanitize_key((string) (self::get()[self::GEOCODING_PROVIDER] ?? self::GEOCODER_NOMINATIM));

        return in_array($provider, self::GEOCODING_PROVIDERS, true)
            ? $provider
            : self::GEOCODER_NOMINATIM;
    }

    public static function geocodingNominatimEndpoint(): string
    {
        $endpoint = trim((string) (self::get()[self::GEOCODING_NOMINATIM_ENDPOINT] ?? ''));

        return $endpoint !== '' ? $endpoint : self::DEFAULT_NOMINATIM_ENDPOINT;
    }

    /**
     * Identifizierender User-Agent für Nominatim (Usage Policy).
     */
    public static function geocodingUserAgent(): string
    {
        $site = home_url('/');
        $contact = sanitize_email((string) get_option('admin_email', ''));
        $version = defined('BS_FABRIC_VERSION') ? (string) BS_FABRIC_VERSION : '1.0.0';
        $ua = sprintf('bs-fabric/%s (+%s)', $version, $site);

        if ($contact !== '') {
            $ua .= '; contact: ' . $contact;
        }

        return $ua;
    }

    /**
     * @param array<string, mixed> $input
     *
     * @return array<string, mixed>
     */
    private static function sanitize(array $input): array
    {
        $menuLabel = isset($input[self::MENU_LABEL])
            ? sanitize_text_field((string) $input[self::MENU_LABEL])
            : (string) self::defaults()[self::MENU_LABEL];

        if ($menuLabel === '') {
            $menuLabel = (string) self::defaults()[self::MENU_LABEL];
        }

        $menuLabel = mb_substr($menuLabel, 0, 50);

        $status = isset($input[self::DEFAULT_ENTRY_STATUS])
            ? sanitize_key((string) $input[self::DEFAULT_ENTRY_STATUS])
            : 'draft';

        if (! in_array($status, self::ENTRY_STATUSES, true)) {
            $status = 'draft';
        }

        $provider = isset($input[self::GEOCODING_PROVIDER])
            ? sanitize_key((string) $input[self::GEOCODING_PROVIDER])
            : self::GEOCODER_NOMINATIM;

        if (! in_array($provider, self::GEOCODING_PROVIDERS, true)) {
            $provider = self::GEOCODER_NOMINATIM;
        }

        $endpoint = isset($input[self::GEOCODING_NOMINATIM_ENDPOINT])
            ? self::sanitizeNominatimEndpoint((string) $input[self::GEOCODING_NOMINATIM_ENDPOINT])
            : self::DEFAULT_NOMINATIM_ENDPOINT;

        return [
            self::MENU_LABEL => $menuLabel,
            self::DEFAULT_ENTRY_STATUS => $status,
            self::LOCK_FIELD_KEYS => ! empty($input[self::LOCK_FIELD_KEYS]),
            self::DELETE_ON_UNINSTALL => ! empty($input[self::DELETE_ON_UNINSTALL]),
            self::GEOCODING_PROVIDER => $provider,
            self::GEOCODING_NOMINATIM_ENDPOINT => $endpoint,
        ];
    }

    private static function sanitizeNominatimEndpoint(string $endpoint): string
    {
        $endpoint = esc_url_raw(trim($endpoint));

        if ($endpoint === '') {
            return self::DEFAULT_NOMINATIM_ENDPOINT;
        }

        if (! preg_match('#^https?://#i', $endpoint)) {
            return self::DEFAULT_NOMINATIM_ENDPOINT;
        }

        return untrailingslashit($endpoint);
    }
}
