<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class SelectField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'select';
    }

    public function getLabel(): string
    {
        return __('Select', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'options' => [],
            'multiple' => false,
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => is_scalar($item) ? sanitize_text_field((string) $item) : '',
                $value
            )));
        }

        return is_scalar($value) ? sanitize_text_field((string) $value) : '';
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $multiple = (bool) $settings['multiple'];
        $nameAttr = $multiple ? $name . '[]' : $name;
        $selectedValues = $this->normalizeSelectedValues($value);
        $options = $this->normalizeOptions($settings['options']);
        $optionsHtml = '';

        foreach ($options as $option) {
            $isSelected = in_array($option['value'], $selectedValues, true);
            $optionsHtml .= sprintf(
                '<option value="%s"%s>%s</option>',
                esc_attr($option['value']),
                selected($isSelected, true, false),
                esc_html($option['label'])
            );
        }

        return sprintf(
            '<select class="bs-fabric-field bs-fabric-field-select" name="%s"%s>%s</select>',
            esc_attr($nameAttr),
            $multiple ? ' multiple' : '',
            $optionsHtml
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);
        $options = $this->normalizeOptions($settings['options']);
        $optionsText = '';

        foreach ($options as $option) {
            $optionsText .= $option['value'] . '|' . $option['label'] . "\n";
        }

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-options">%2$s</label>'
            . '<textarea id="%1$s-options" name="%1$s[options]" rows="6" class="large-text code">%3$s</textarea>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%1$s[multiple]" value="1"%4$s /> %5$s</label>'
            . '</p>',
            esc_attr($name),
            esc_html__('Optionen (value|Label pro Zeile)', 'bs-fabric'),
            esc_textarea(trim($optionsText)),
            checked((bool) $settings['multiple'], true, false),
            esc_html__('Mehrfachauswahl', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_array($value)) {
            return array_map(
                static fn (mixed $item): string => is_scalar($item) ? esc_html((string) $item) : '',
                $value
            );
        }

        return is_scalar($value) ? esc_html((string) $value) : '';
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

    /**
     * @return list<string>
     */
    private function normalizeSelectedValues(mixed $value): array
    {
        if (is_array($value)) {
            return array_values(array_filter(array_map(
                static fn (mixed $item): string => is_scalar($item) ? (string) $item : '',
                $value
            )));
        }

        if (is_scalar($value) && $value !== '') {
            return [(string) $value];
        }

        return [];
    }

    /**
     * @param mixed $options
     *
     * @return list<array{value: string, label: string}>
     */
    private function normalizeOptions(mixed $options): array
    {
        if (! is_array($options)) {
            return [];
        }

        $normalized = [];

        foreach ($options as $key => $option) {
            if (is_array($option)) {
                $value = isset($option['value']) && is_scalar($option['value']) ? (string) $option['value'] : '';
                $label = isset($option['label']) && is_scalar($option['label']) ? (string) $option['label'] : $value;
            } elseif (is_scalar($option)) {
                $value = is_string($key) ? $key : (string) $option;
                $label = (string) $option;
            } else {
                continue;
            }

            if ($value === '') {
                continue;
            }

            $normalized[] = [
                'value' => $value,
                'label' => $label,
            ];
        }

        return $normalized;
    }
}
