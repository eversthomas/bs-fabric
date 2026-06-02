<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class TextField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'text';
    }

    public function getLabel(): string
    {
        return __('Text', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'placeholder' => '',
            'max_length' => 0,
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

        $sanitized = sanitize_text_field((string) $value);

        return $sanitized;
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $stringValue = is_scalar($value) ? (string) $value : '';
        $maxLength = (int) $settings['max_length'];
        $maxLengthAttr = $maxLength > 0 ? sprintf(' maxlength="%d"', $maxLength) : '';

        return sprintf(
            '<input type="text" class="bs-fabric-field bs-fabric-field-text" name="%s" value="%s" placeholder="%s"%s />',
            esc_attr($name),
            esc_attr($stringValue),
            esc_attr((string) $settings['placeholder']),
            $maxLengthAttr
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-placeholder">%2$s</label>'
            . '<input type="text" id="%1$s-placeholder" name="%1$s[placeholder]" value="%3$s" class="regular-text" />'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-max_length">%4$s</label>'
            . '<input type="number" id="%1$s-max_length" name="%1$s[max_length]" value="%5$d" min="0" class="small-text" />'
            . '</p>',
            esc_attr($name),
            esc_html__('Placeholder', 'bs-fabric'),
            esc_attr((string) $settings['placeholder']),
            esc_html__('Maximale Länge (0 = unbegrenzt)', 'bs-fabric'),
            (int) $settings['max_length']
        );
    }

    public function renderValue(mixed $value): mixed
    {
        if (! is_scalar($value) || $value === '') {
            return '';
        }

        return esc_html((string) $value);
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
