<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class NumberField implements FieldTypeInterface
{
    use SanitizesFieldSettings;

    public function getType(): string
    {
        return 'number';
    }

    public function getLabel(): string
    {
        return __('Zahl', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'min' => '',
            'max' => '',
            'step' => '1',
            'unit' => '',
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (! is_scalar($value)) {
            return '';
        }

        $stringValue = str_replace(',', '.', trim((string) $value));

        if ($stringValue === '' || ! is_numeric($stringValue)) {
            return '';
        }

        $number = str_contains($stringValue, '.') ? (float) $stringValue : (int) $stringValue;

        return is_finite($number) ? $number : '';
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $stringValue = is_scalar($value) && $value !== '' ? (string) $value : '';
        $min = $this->boundAttribute($settings['min'] ?? '');
        $max = $this->boundAttribute($settings['max'] ?? '');
        $step = $this->stepAttribute($settings['step'] ?? '1');
        $unit = trim((string) ($settings['unit'] ?? ''));

        $input = sprintf(
            '<input type="number" class="bs-fabric-field bs-fabric-field-number" name="%s" value="%s"%s%s%s />',
            esc_attr($name),
            esc_attr($stringValue),
            $min,
            $max,
            $step
        );

        if ($unit === '') {
            return '<div class="bs-fabric-field bs-fabric-field-number-wrap">' . $input . '</div>';
        }

        return sprintf(
            '<div class="bs-fabric-field bs-fabric-field-number-wrap">%s<span class="bs-fabric-field-number-unit">%s</span></div>',
            $input,
            esc_html($unit)
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-min">%2$s</label>'
            . '<input type="number" id="%1$s-min" name="%1$s[min]" value="%3$s" class="small-text" step="any" />'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-max">%4$s</label>'
            . '<input type="number" id="%1$s-max" name="%1$s[max]" value="%5$s" class="small-text" step="any" />'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-step">%6$s</label>'
            . '<input type="text" id="%1$s-step" name="%1$s[step]" value="%7$s" class="small-text" />'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-unit">%8$s</label>'
            . '<input type="text" id="%1$s-unit" name="%1$s[unit]" value="%9$s" class="regular-text" />'
            . '</p>',
            esc_attr($name),
            esc_html__('Minimum (optional)', 'bs-fabric'),
            esc_attr($this->settingScalar($settings['min'] ?? '')),
            esc_html__('Maximum (optional)', 'bs-fabric'),
            esc_attr($this->settingScalar($settings['max'] ?? '')),
            esc_html__('Schrittweite', 'bs-fabric'),
            esc_attr((string) ($settings['step'] ?? '1')),
            esc_html__('Einheit (optional)', 'bs-fabric'),
            esc_attr((string) ($settings['unit'] ?? ''))
        );
    }

    public function renderValue(mixed $value): mixed
    {
        $sanitized = $this->sanitizeValue($value);

        if ($sanitized === '') {
            return null;
        }

        return $sanitized;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeTypeSettings(array $settings): array
    {
        $settings = $this->sanitizeStringSetting($settings, 'unit');

        foreach (['min', 'max'] as $key) {
            if (! array_key_exists($key, $settings)) {
                continue;
            }

            $raw = trim((string) $settings[$key]);

            if ($raw === '') {
                $settings[$key] = '';
                continue;
            }

            $settings[$key] = is_numeric(str_replace(',', '.', $raw))
                ? (str_contains($raw, '.') ? (float) str_replace(',', '.', $raw) : (int) $raw)
                : '';
        }

        if (array_key_exists('step', $settings)) {
            $step = trim((string) $settings['step']);
            $settings['step'] = $step === '' || ! is_numeric(str_replace(',', '.', $step))
                ? '1'
                : str_replace(',', '.', $step);
        }

        return $settings;
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

    private function boundAttribute(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        if (! is_scalar($value) || ! is_numeric((string) $value)) {
            return '';
        }

        return sprintf(' min="%s"', esc_attr((string) $value));
    }

    private function stepAttribute(mixed $value): string
    {
        $step = is_scalar($value) ? trim((string) $value) : '1';

        if ($step === '' || ! is_numeric(str_replace(',', '.', $step))) {
            $step = '1';
        }

        return sprintf(' step="%s"', esc_attr(str_replace(',', '.', $step)));
    }

    private function settingScalar(mixed $value): string
    {
        if ($value === '' || $value === null) {
            return '';
        }

        return is_scalar($value) ? (string) $value : '';
    }
}
