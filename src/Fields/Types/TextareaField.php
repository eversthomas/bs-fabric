<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class TextareaField implements FieldTypeInterface
{
    use SanitizesFieldSettings;
    public function getType(): string
    {
        return 'textarea';
    }

    public function getLabel(): string
    {
        return __('Textarea', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'placeholder' => '',
            'rows' => 5,
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

        return sanitize_textarea_field((string) $value);
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $stringValue = is_scalar($value) ? (string) $value : '';

        return sprintf(
            '<textarea class="bs-fabric-field bs-fabric-field-textarea" name="%s" rows="%d" placeholder="%s">%s</textarea>',
            esc_attr($name),
            (int) $settings['rows'],
            esc_attr((string) $settings['placeholder']),
            esc_textarea($stringValue)
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
            . '<label for="%1$s-rows">%4$s</label>'
            . '<input type="number" id="%1$s-rows" name="%1$s[rows]" value="%5$d" min="2" class="small-text" />'
            . '</p>',
            esc_attr($name),
            esc_html__('Placeholder', 'bs-fabric'),
            esc_attr((string) $settings['placeholder']),
            esc_html__('Zeilen', 'bs-fabric'),
            (int) $settings['rows']
        );
    }

    public function renderValue(mixed $value): mixed
    {
        if (! is_scalar($value) || $value === '') {
            return '';
        }

        return nl2br(esc_html((string) $value));
    }

    /**
     * @param array<string, mixed> $settings
     *
     * @return array<string, mixed>
     */
    protected function sanitizeTypeSettings(array $settings): array
    {
        $settings = $this->sanitizeStringSetting($settings, 'placeholder');
        $settings = $this->sanitizeIntSetting($settings, 'rows', 2);

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
