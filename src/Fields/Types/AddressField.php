<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class AddressField implements FieldTypeInterface
{
    use SanitizesFieldSettings;

    /** @var array<string, string> */
    private const COUNTRIES = [
        'DE' => 'Deutschland',
        'AT' => 'Österreich',
        'CH' => 'Schweiz',
        'LI' => 'Liechtenstein',
        'LU' => 'Luxemburg',
        'BE' => 'Belgien',
        'NL' => 'Niederlande',
        'FR' => 'Frankreich',
        'IT' => 'Italien',
        'PL' => 'Polen',
        'CZ' => 'Tschechien',
        'DK' => 'Dänemark',
        'SE' => 'Schweden',
        'NO' => 'Norwegen',
        'GB' => 'Vereinigtes Königreich',
        'US' => 'USA',
    ];

    public function getType(): string
    {
        return 'address';
    }

    public function getLabel(): string
    {
        return __('Adresse', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'require_street' => true,
            'require_zip' => true,
            'require_city' => true,
            'show_country' => true,
            'default_country' => 'DE',
            'enable_geocoding' => false,
            'enable_map_picker' => false,
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        $raw = $this->normalizeRawValue($value);
        $parts = [
            'street' => $this->sanitizeStreet($raw['street'] ?? ''),
            'zip' => $this->sanitizeZip($raw['zip'] ?? ''),
            'city' => $this->sanitizeCity($raw['city'] ?? ''),
            'country' => $this->sanitizeCountry($raw['country'] ?? ''),
        ];

        if ($this->isAddressEmpty($parts)) {
            return null;
        }

        $normalized = $parts;
        $normalized['formatted'] = $this->buildFormatted($parts);

        foreach (['lat', 'lng', 'geocoded_at', 'geocode_status', 'geocode_hash'] as $geoKey) {
            if (! array_key_exists($geoKey, $raw)) {
                continue;
            }

            $preserved = $this->sanitizeGeoField($geoKey, $raw[$geoKey]);

            if ($preserved !== null && $preserved !== '') {
                $normalized[$geoKey] = $preserved;
            }
        }

        return $normalized;
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $raw = $this->normalizeRawValue($value);
        $baseName = $this->fieldName($field);
        $templateRequired = ! empty($field['_template_required']);
        $showCountry = (bool) ($settings['show_country'] ?? true);
        $defaultCountry = $this->sanitizeCountry((string) ($settings['default_country'] ?? 'DE'));

        $streetRequired = $templateRequired || ! empty($settings['require_street']);
        $zipRequired = $templateRequired || ! empty($settings['require_zip']);
        $cityRequired = $templateRequired || ! empty($settings['require_city']);

        $street = esc_attr($raw['street'] ?? '');
        $zip = esc_attr($raw['zip'] ?? '');
        $city = esc_attr($raw['city'] ?? '');
        $country = $this->sanitizeCountry($raw['country'] ?? '') ?: $defaultCountry;

        $html = '<div class="bs-fabric-field bs-fabric-field-address"'
            . (! empty($settings['enable_map_picker']) ? ' data-map-picker="1"' : '')
            . '>';

        $html .= $this->renderSubfield(
            $baseName . '[street]',
            __('Straße', 'bs-fabric'),
            $street,
            'text',
            $streetRequired,
            'bs-fabric-field-address-street'
        );

        $html .= '<div class="bs-fabric-field-address-row">';

        $html .= $this->renderSubfield(
            $baseName . '[zip]',
            __('PLZ', 'bs-fabric'),
            $zip,
            'text',
            $zipRequired,
            'bs-fabric-field-address-zip',
            false
        );

        $html .= $this->renderSubfield(
            $baseName . '[city]',
            __('Ort', 'bs-fabric'),
            $city,
            'text',
            $cityRequired,
            'bs-fabric-field-address-city',
            false
        );

        $html .= '</div>';

        if ($showCountry) {
            $html .= '<div class="bs-fabric-field-address-subfield bs-fabric-field-address-country">';
            $html .= '<label class="bs-fabric-field-address-label">' . esc_html__('Land', 'bs-fabric') . '</label>';
            $html .= '<select class="bs-fabric-field bs-fabric-field-address-select" name="' . esc_attr($baseName . '[country]') . '">';

            foreach (self::COUNTRIES as $code => $label) {
                $html .= sprintf(
                    '<option value="%1$s"%2$s>%3$s</option>',
                    esc_attr($code),
                    selected($country, $code, false),
                    esc_html($label)
                );
            }

            $html .= '</select></div>';
        } else {
            $html .= sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" />',
                esc_attr($baseName . '[country]'),
                esc_attr($defaultCountry)
            );
        }

        $needsGeoInputs = ! empty($settings['enable_geocoding']) || ! empty($settings['enable_map_picker']);
        $html .= $this->renderGeoHiddenFields($baseName, $raw, $needsGeoInputs);
        $html .= $this->renderMapPicker($raw, $settings);
        $html .= $this->renderGeocodeStatusHint($raw, $settings);

        $html .= '</div>';

        return $html;
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<fieldset class="bs-fabric-field-setting-group">'
            . '<legend>%1$s</legend>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%2$s[require_street]" value="1"%3$s /> %4$s</label>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%2$s[require_zip]" value="1"%5$s /> %6$s</label>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%2$s[require_city]" value="1"%7$s /> %8$s</label>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%2$s[show_country]" value="1"%9$s /> %10$s</label>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label for="%2$s-default_country">%11$s</label>'
            . '<select id="%2$s-default_country" name="%2$s[default_country]" class="regular-text">%12$s</select>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%2$s[enable_geocoding]" value="1"%13$s /> %14$s</label>'
            . '<span class="description"> %15$s</span>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%2$s[enable_map_picker]" value="1"%16$s /> %17$s</label>'
            . '<span class="description"> %18$s</span>'
            . '</p>'
            . '</fieldset>',
            esc_html__('Adress-Einstellungen', 'bs-fabric'),
            esc_attr($name),
            checked(! empty($settings['require_street']), true, false),
            esc_html__('Straße ist Pflichtfeld', 'bs-fabric'),
            checked(! empty($settings['require_zip']), true, false),
            esc_html__('PLZ ist Pflichtfeld', 'bs-fabric'),
            checked(! empty($settings['require_city']), true, false),
            esc_html__('Ort ist Pflichtfeld', 'bs-fabric'),
            checked(! empty($settings['show_country']), true, false),
            esc_html__('Land-Feld anzeigen', 'bs-fabric'),
            esc_html__('Standard-Land', 'bs-fabric'),
            $this->renderCountryOptions((string) ($settings['default_country'] ?? 'DE')),
            checked(! empty($settings['enable_geocoding']), true, false),
            esc_html__('Geocoding beim Speichern', 'bs-fabric'),
            esc_html__('Speichert Lat/Lng über den konfigurierten Provider (Einstellungen → Geo).', 'bs-fabric'),
            checked(! empty($settings['enable_map_picker']), true, false),
            esc_html__('Karte im Entry-Editor', 'bs-fabric'),
            esc_html__('Pin setzen/verschieben überschreibt die Koordinaten (Leaflet + OSM).', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        $sanitized = $this->sanitizeValue($value);

        if (! is_array($sanitized)) {
            return null;
        }

        $mapsUrl = self::buildMapsUrl($sanitized);

        if ($mapsUrl !== null) {
            $sanitized['maps_url'] = $mapsUrl;
        }

        return $sanitized;
    }

    /**
     * OpenStreetMap-Link für lat/lng (kein Google).
     *
     * @param array<string, mixed> $address
     */
    public static function buildMapsUrl(array $address): ?string
    {
        if (! isset($address['lat'], $address['lng']) || ! is_numeric($address['lat']) || ! is_numeric($address['lng'])) {
            return null;
        }

        $lat = (float) $address['lat'];
        $lng = (float) $address['lng'];

        if (! is_finite($lat) || ! is_finite($lng)) {
            return null;
        }

        $url = sprintf(
            'https://www.openstreetmap.org/?mlat=%1$s&mlon=%2$s#map=16/%1$s/%2$s',
            rawurlencode((string) $lat),
            rawurlencode((string) $lng)
        );

        /**
         * @param string $url
         * @param array<string, mixed> $addressValue
         */
        $filtered = apply_filters('bs_fabric_address_maps_url', $url, $address);

        return is_string($filtered) && $filtered !== '' ? $filtered : $url;
    }

    /**
     * @return list<string>
     */
    public function getValidationErrors(mixed $value, array $field, bool $templateRequired): array
    {
        $settings = $this->mergeSettings($field);
        $raw = $this->normalizeRawValue($value);
        $parts = [
            'street' => $this->sanitizeStreet($raw['street'] ?? ''),
            'zip' => $this->sanitizeZip($raw['zip'] ?? ''),
            'city' => $this->sanitizeCity($raw['city'] ?? ''),
            'country' => $this->sanitizeCountry($raw['country'] ?? ''),
        ];
        $label = (string) ($field['label'] ?? $field['name'] ?? '');

        if ($this->isAddressEmpty($parts)) {
            if ($templateRequired) {
                return [sprintf(
                    /* translators: %s: field label */
                    __('Pflichtfeld „%s“ ist leer.', 'bs-fabric'),
                    $label
                )];
            }

            return [];
        }

        $errors = [];

        if (! empty($settings['require_street']) && $parts['street'] === '') {
            $errors[] = sprintf(
                /* translators: 1: field label */
                __('„%1$s“: Straße ist erforderlich.', 'bs-fabric'),
                $label
            );
        }

        if (! empty($settings['require_zip']) && $parts['zip'] === '') {
            $errors[] = sprintf(
                /* translators: 1: field label */
                __('„%1$s“: PLZ ist erforderlich.', 'bs-fabric'),
                $label
            );
        }

        if (! empty($settings['require_city']) && $parts['city'] === '') {
            $errors[] = sprintf(
                /* translators: 1: field label */
                __('„%1$s“: Ort ist erforderlich.', 'bs-fabric'),
                $label
            );
        }

        return $errors;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeTypeSettings(array $settings): array
    {
        foreach (['require_street', 'require_zip', 'require_city', 'show_country', 'enable_geocoding', 'enable_map_picker'] as $key) {
            $settings = $this->sanitizeBoolSetting($settings, $key);
        }

        if (array_key_exists('default_country', $settings)) {
            $settings['default_country'] = $this->sanitizeCountry((string) $settings['default_country']) ?: 'DE';
        }

        return $settings;
    }

    /**
     * @return array<string, mixed>
     */
    private function normalizeRawValue(mixed $value): array
    {
        if (is_string($value) && $value !== '') {
            $decoded = json_decode($value, true);

            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param array{street?: string, zip?: string, city?: string, country?: string} $parts
     */
    private function isAddressEmpty(array $parts): bool
    {
        return ($parts['street'] ?? '') === ''
            && ($parts['zip'] ?? '') === ''
            && ($parts['city'] ?? '') === '';
    }

    /**
     * @param array{street: string, zip: string, city: string, country: string} $parts
     */
    private function buildFormatted(array $parts): string
    {
        $segments = [];

        if ($parts['street'] !== '') {
            $segments[] = $parts['street'];
        }

        $cityLine = trim($parts['zip'] . ' ' . $parts['city']);

        if ($cityLine !== '') {
            $segments[] = $cityLine;
        }

        return implode(', ', $segments);
    }

    private function sanitizeStreet(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }

    private function sanitizeZip(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }

    private function sanitizeCity(mixed $value): string
    {
        return sanitize_text_field((string) $value);
    }

    private function sanitizeCountry(mixed $value): string
    {
        $country = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) $value) ?? '');

        if (strlen($country) !== 2 || ! ctype_alpha($country)) {
            return '';
        }

        return $country;
    }

    private function sanitizeGeoField(string $key, mixed $value): mixed
    {
        if ($key === 'lat' || $key === 'lng') {
            if ($value === null || $value === '') {
                return null;
            }

            if (! is_numeric($value)) {
                return null;
            }

            $float = (float) $value;

            return is_finite($float) ? $float : null;
        }

        if ($key === 'geocoded_at') {
            $string = sanitize_text_field((string) $value);

            return $string !== '' ? $string : null;
        }

        if ($key === 'geocode_status') {
            $status = sanitize_key((string) $value);

            return in_array($status, ['ok', 'failed', 'manual'], true) ? $status : null;
        }

        if ($key === 'geocode_hash') {
            $hash = preg_replace('/[^a-f0-9]/', '', strtolower((string) $value)) ?? '';

            return strlen($hash) === 64 ? $hash : null;
        }

        return null;
    }

    /**
     * @param array<string, mixed> $raw
     */
    private function renderGeoHiddenFields(string $baseName, array $raw, bool $alwaysIncludeCoords = false): string
    {
        $html = '';
        $keys = ['lat', 'lng', 'geocoded_at', 'geocode_status', 'geocode_hash'];

        foreach ($keys as $key) {
            $hasValue = array_key_exists($key, $raw) && $raw[$key] !== null && $raw[$key] !== '';
            $forceEmpty = $alwaysIncludeCoords && in_array($key, ['lat', 'lng', 'geocode_status', 'geocode_hash'], true);

            if (! $hasValue && ! $forceEmpty) {
                continue;
            }

            $value = $hasValue ? $raw[$key] : '';

            if (($key === 'lat' || $key === 'lng') && $hasValue) {
                if (! is_numeric($value)) {
                    if (! $forceEmpty) {
                        continue;
                    }

                    $value = '';
                } else {
                    $value = (string) $value;
                }
            } elseif ($hasValue) {
                $value = (string) $value;
            } else {
                $value = '';
            }

            $html .= sprintf(
                '<input type="hidden" name="%1$s" value="%2$s" data-geo-key="%3$s" />',
                esc_attr($baseName . '[' . $key . ']'),
                esc_attr($value),
                esc_attr($key)
            );
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $settings
     */
    private function renderMapPicker(array $raw, array $settings): string
    {
        if (empty($settings['enable_map_picker'])) {
            return '';
        }

        $lat = $raw['lat'] ?? null;
        $lng = $raw['lng'] ?? null;
        $hasCoords = is_numeric($lat) && is_numeric($lng);
        $status = sanitize_key((string) ($raw['geocode_status'] ?? ''));

        $coordsClass = 'bs-fabric-field-address-map-coords bs-fabric-field-address-geocode';
        $coordsText = esc_html__('Karte anklicken oder Pin ziehen, um Koordinaten zu setzen.', 'bs-fabric');

        if ($hasCoords) {
            $coordsClass .= $status === 'manual' ? ' is-manual' : ' is-ok';
            $coordsText = esc_html(sprintf(
                /* translators: 1: latitude, 2: longitude */
                __('Pin: %1$s, %2$s', 'bs-fabric'),
                (string) $lat,
                (string) $lng
            ));
        } else {
            $coordsClass .= ' is-pending';
        }

        return '<div class="bs-fabric-field-address-map-wrap">'
            . '<div class="bs-fabric-field-address-map" role="application" aria-label="'
            . esc_attr__('Standort auf Karte setzen', 'bs-fabric')
            . '"></div>'
            . '<p class="' . esc_attr($coordsClass) . '">' . $coordsText . '</p>'
            . '</div>';
    }

    /**
     * @param array<string, mixed> $raw
     * @param array<string, mixed> $settings
     */
    private function renderGeocodeStatusHint(array $raw, array $settings): string
    {
        // Map-Picker zeigt eigene Koordinatenzeile.
        if (! empty($settings['enable_map_picker'])) {
            return '';
        }

        if (empty($settings['enable_geocoding'])) {
            return '';
        }

        $status = sanitize_key((string) ($raw['geocode_status'] ?? ''));
        $lat = $raw['lat'] ?? null;
        $lng = $raw['lng'] ?? null;

        if (($status === 'ok' || $status === 'manual') && is_numeric($lat) && is_numeric($lng)) {
            return sprintf(
                '<p class="bs-fabric-field-address-geocode is-ok">%s</p>',
                esc_html(sprintf(
                    /* translators: 1: latitude, 2: longitude */
                    __('Koordinaten: %1$s, %2$s', 'bs-fabric'),
                    (string) $lat,
                    (string) $lng
                ))
            );
        }

        if ($status === 'failed') {
            return sprintf(
                '<p class="bs-fabric-field-address-geocode is-failed">%s</p>',
                esc_html__('Geocoding fehlgeschlagen — Adresse prüfen oder erneut speichern.', 'bs-fabric')
            );
        }

        return sprintf(
            '<p class="bs-fabric-field-address-geocode is-pending">%s</p>',
            esc_html__('Koordinaten werden beim Speichern ermittelt.', 'bs-fabric')
        );
    }

    private function renderSubfield(
        string $name,
        string $label,
        string $value,
        string $type,
        bool $required,
        string $class,
        bool $wrapSubfield = true
    ): string {
        $requiredAttr = $required ? ' required' : '';
        $input = sprintf(
            '<input type="%1$s" class="bs-fabric-field %2$s" name="%3$s" value="%4$s"%5$s />',
            esc_attr($type),
            esc_attr($class),
            esc_attr($name),
            $value,
            $requiredAttr
        );

        if (! $wrapSubfield) {
            return '<div class="bs-fabric-field-address-subfield">'
                . '<label class="bs-fabric-field-address-label">' . esc_html($label) . '</label>'
                . $input
                . '</div>';
        }

        return '<div class="bs-fabric-field-address-subfield">'
            . '<label class="bs-fabric-field-address-label">' . esc_html($label) . '</label>'
            . $input
            . '</div>';
    }

    private function renderCountryOptions(string $selected): string
    {
        $html = '';

        foreach (self::COUNTRIES as $code => $label) {
            $html .= sprintf(
                '<option value="%1$s"%2$s>%3$s</option>',
                esc_attr($code),
                selected($selected, $code, false),
                esc_html($label)
            );
        }

        return $html;
    }

    /**
     * @param array<string, mixed> $field
     *
     * @return array<string, mixed>
     */
    private function mergeSettings(array $field): array
    {
        $stored = is_array($field['settings'] ?? null) ? $field['settings'] : [];

        return array_merge($this->getDefaultSettings(), $stored);
    }

    /**
     * @param array<string, mixed> $field
     */
    private function fieldName(array $field): string
    {
        return 'bs_fabric_fields[' . (string) ($field['name'] ?? '') . ']';
    }

    /**
     * @param array<string, mixed> $field
     */
    private function settingsName(array $field): string
    {
        return 'bs_fabric_field_settings[' . (string) ($field['name'] ?? '') . ']';
    }
}
