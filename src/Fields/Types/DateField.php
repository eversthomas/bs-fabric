<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class DateField implements FieldTypeInterface
{
    use SanitizesFieldSettings;
    public function getType(): string
    {
        return 'date';
    }

    public function getLabel(): string
    {
        return __('Date', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'include_time' => false,
            'format' => 'Y-m-d',
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

        $stringValue = trim((string) $value);
        $format = str_contains($stringValue, ':') ? 'Y-m-d H:i:s' : 'Y-m-d';
        $date = \DateTimeImmutable::createFromFormat($format, $stringValue);

        if ($date === false) {
            return '';
        }

        return $date->format($format);
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $stringValue = is_scalar($value) ? (string) $value : '';
        $inputType = (bool) $settings['include_time'] ? 'datetime-local' : 'date';

        return sprintf(
            '<input type="%s" class="bs-fabric-field bs-fabric-field-date" name="%s" value="%s" />',
            esc_attr($inputType),
            esc_attr($name),
            esc_attr($stringValue)
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%1$s[include_time]" value="1"%2$s /> %3$s</label>'
            . '</p>',
            esc_attr($name),
            checked((bool) $settings['include_time'], true, false),
            esc_html__('Uhrzeit einschließen', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        if (! is_scalar($value) || $value === '') {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y-m-d H:i:s', (string) $value)
            ?: \DateTimeImmutable::createFromFormat('Y-m-d', (string) $value);

        if ($date === false) {
            return esc_html((string) $value);
        }

        return $date;
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeTypeSettings(array $settings): array
    {
        $settings = $this->sanitizeBoolSetting($settings, 'include_time');
        $settings = $this->sanitizeStringSetting($settings, 'format');

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
}
