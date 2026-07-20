<?php

declare(strict_types=1);

namespace BsFabric\Geo;

use BsFabric\Core\PluginSettings;
use BsFabric\Entries\EntryRepository;
use BsFabric\Templates\TemplateRepository;

/**
 * Geocodiert address-Felder mit enable_geocoding nach Entry-Save.
 * Kein Frontend-Request — nur über bs_fabric_after_entry_save.
 */
final class AddressGeocodingService
{
    public const SERVICE_ID = 'geo.address_geocoding';

    public const NOTICE_TRANSIENT = 'bs_fabric_geocode_notice';

    public function __construct(
        private readonly EntryRepository $entryRepository,
        private readonly TemplateRepository $templateRepository,
        private readonly GeocoderFactory $geocoderFactory,
    ) {
    }

    public function register(): void
    {
        add_action('bs_fabric_after_entry_save', [$this, 'onAfterEntrySave'], 20, 2);
    }

    /**
     * @param array<string, mixed> $data
     */
    public function onAfterEntrySave(int $entryId, array $data): void
    {
        if ($entryId <= 0) {
            return;
        }

        // Nur wenn Feldwerte mitgespeichert wurden — Status-only-Updates überspringen.
        if (! array_key_exists('fields', $data)) {
            return;
        }

        $entry = $this->entryRepository->getById($entryId);

        if ($entry === null) {
            return;
        }

        $templateId = (int) $entry->template_id;
        $templateFields = $this->templateRepository->getFields($templateId);
        $rawValues = $entry->toArray();
        $notices = [];
        $apiCalls = 0;

        foreach ($templateFields as $field) {
            if (($field['type'] ?? '') !== 'address') {
                continue;
            }

            $settings = is_array($field['settings'] ?? null) ? $field['settings'] : [];

            if (empty($settings['enable_geocoding'])) {
                continue;
            }

            $fieldName = (string) ($field['name'] ?? '');
            $fieldId = (int) ($field['field_id'] ?? 0);

            if ($fieldName === '' || $fieldId <= 0) {
                continue;
            }

            $value = $rawValues[$fieldName] ?? null;

            if (! is_array($value)) {
                continue;
            }

            $result = $this->geocodeAddressField($value, $apiCalls);

            if ($result === null) {
                continue;
            }

            [$updatedValue, $notice, $didApiCall] = $result;

            if ($didApiCall) {
                ++$apiCalls;
            }

            $this->entryRepository->updateFieldValue($entryId, $fieldId, $updatedValue);
            $rawValues[$fieldName] = $updatedValue;

            if ($notice !== null) {
                $label = (string) ($field['label'] ?? $fieldName);
                $notices[] = sprintf('%s: %s', $label, $notice);
            }
        }

        if ($notices !== []) {
            $this->storeAdminNotice(implode(' ', $notices));
        }
    }

    /**
     * @param array<string, mixed> $value
     *
     * @return array{0: array<string, mixed>, 1: string|null, 2: bool}|null Null = keine Änderung nötig
     */
    private function geocodeAddressField(array $value, int $previousApiCalls): ?array
    {
        $street = (string) ($value['street'] ?? '');
        $zip = (string) ($value['zip'] ?? '');
        $city = (string) ($value['city'] ?? '');
        $country = (string) ($value['country'] ?? '');

        if ($street === '' && $zip === '' && $city === '') {
            return null;
        }

        $fingerprint = self::fingerprint([
            'street' => $street,
            'zip' => $zip,
            'city' => $city,
            'country' => $country,
        ]);

        $existingHash = (string) ($value['geocode_hash'] ?? '');
        $existingStatus = (string) ($value['geocode_status'] ?? '');

        if (
            $existingHash === $fingerprint
            && $existingStatus === GeocodeResult::STATUS_OK
            && isset($value['lat'], $value['lng'])
            && is_numeric($value['lat'])
            && is_numeric($value['lng'])
        ) {
            return null;
        }

        $geocoder = $this->geocoderFactory->create();

        if ($geocoder === null) {
            $updated = $value;
            $updated['geocode_status'] = GeocodeResult::STATUS_FAILED;
            $updated['geocode_hash'] = $fingerprint;
            unset($updated['lat'], $updated['lng'], $updated['geocoded_at']);

            return [
                $updated,
                __('Geocoding ist deaktiviert (Provider „none“).', 'bs-fabric'),
                false,
            ];
        }

        // Nominatim: max. 1 Request/Sekunde bei mehreren Address-Feldern.
        if (
            $previousApiCalls > 0
            && PluginSettings::geocodingProvider() === PluginSettings::GEOCODER_NOMINATIM
        ) {
            usleep(1_100_000);
        }

        $result = $geocoder->geocode([
            'street' => $street,
            'zip' => $zip,
            'city' => $city,
            'country' => $country,
            'formatted' => (string) ($value['formatted'] ?? ''),
        ]);

        $updated = $value;
        $updated['geocode_hash'] = $fingerprint;
        $updated['geocode_status'] = $result->status;

        if ($result->isOk()) {
            $updated['lat'] = $result->lat;
            $updated['lng'] = $result->lng;
            $updated['geocoded_at'] = gmdate('c');

            return [$updated, null, true];
        }

        unset($updated['lat'], $updated['lng'], $updated['geocoded_at']);

        $message = $result->message !== ''
            ? $result->message
            : __('Geocoding fehlgeschlagen.', 'bs-fabric');

        return [$updated, $message, true];
    }

    /**
     * @param array{street?: string, zip?: string, city?: string, country?: string} $parts
     */
    public static function fingerprint(array $parts): string
    {
        return hash(
            'sha256',
            implode('|', [
                trim((string) ($parts['street'] ?? '')),
                trim((string) ($parts['zip'] ?? '')),
                trim((string) ($parts['city'] ?? '')),
                strtoupper(trim((string) ($parts['country'] ?? ''))),
            ])
        );
    }

    private function storeAdminNotice(string $message): void
    {
        $userId = get_current_user_id();

        if ($userId <= 0 || $message === '') {
            return;
        }

        set_transient(self::NOTICE_TRANSIENT . '_' . $userId, $message, 120);
    }

    public static function consumeAdminNotice(): ?string
    {
        $userId = get_current_user_id();

        if ($userId <= 0) {
            return null;
        }

        $key = self::NOTICE_TRANSIENT . '_' . $userId;
        $message = get_transient($key);

        if (! is_string($message) || $message === '') {
            return null;
        }

        delete_transient($key);

        return $message;
    }
}
