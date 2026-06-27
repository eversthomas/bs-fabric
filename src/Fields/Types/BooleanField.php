<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class BooleanField implements FieldTypeInterface
{
    use SanitizesFieldSettings;

    public function getType(): string
    {
        return 'boolean';
    }

    public function getLabel(): string
    {
        return __('Ja/Nein', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'checkbox_label' => '',
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return false;
        }

        if (is_bool($value)) {
            return $value;
        }

        if (is_numeric($value)) {
            return (int) $value === 1;
        }

        if (is_string($value)) {
            $normalized = strtolower(trim($value));

            return in_array($normalized, ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $checked = $this->sanitizeValue($value);
        $checkboxLabel = (string) $settings['checkbox_label'];

        $labelHtml = $checkboxLabel !== ''
            ? '<span class="bs-fabric-field-boolean-label">' . esc_html($checkboxLabel) . '</span>'
            : '';

        return sprintf(
            '<div class="bs-fabric-field bs-fabric-field-boolean">'
            . '<input type="hidden" name="%1$s" value="0" />'
            . '<label class="bs-fabric-field-boolean-wrap">'
            . '<input type="checkbox" name="%1$s" value="1"%2$s />'
            . '%3$s'
            . '</label>'
            . '</div>',
            esc_attr($name),
            checked($checked, true, false),
            $labelHtml
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-checkbox_label">%2$s</label>'
            . '<input type="text" id="%1$s-checkbox_label" name="%1$s[checkbox_label]" value="%3$s" class="regular-text" />'
            . '</p>',
            esc_attr($name),
            esc_html__('Checkbox-Beschriftung (optional)', 'bs-fabric'),
            esc_attr((string) $settings['checkbox_label'])
        );
    }

    public function renderValue(mixed $value): mixed
    {
        return $this->sanitizeValue($value);
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeTypeSettings(array $settings): array
    {
        return $this->sanitizeStringSetting($settings, 'checkbox_label');
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
