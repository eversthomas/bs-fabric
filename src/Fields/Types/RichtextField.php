<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class RichtextField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'richtext';
    }

    public function getLabel(): string
    {
        return __('Rich Text', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'media_buttons' => true,
            'teeny' => false,
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

        return wp_kses_post((string) $value);
    }

    public function renderInput(array $field, mixed $value): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->fieldName($field);
        $editorId = 'bs-fabric-richtext-' . sanitize_key((string) ($field['name'] ?? 'field'));
        $stringValue = is_scalar($value) ? (string) $value : '';

        if (function_exists('wp_editor')) {
            ob_start();
            wp_editor(
                $stringValue,
                $editorId,
                [
                    'textarea_name' => $name,
                    'media_buttons' => (bool) $settings['media_buttons'],
                    'teeny' => (bool) $settings['teeny'],
                    'textarea_rows' => 10,
                    'editor_class' => 'bs-fabric-field bs-fabric-field-richtext',
                ]
            );

            return (string) ob_get_clean();
        }

        return sprintf(
            '<textarea class="bs-fabric-field bs-fabric-field-richtext" name="%s" rows="10">%s</textarea>',
            esc_attr($name),
            esc_textarea($stringValue)
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%1$s[media_buttons]" value="1"%2$s /> %3$s</label>'
            . '</p>'
            . '<p class="bs-fabric-field-setting">'
            . '<label><input type="checkbox" name="%1$s[teeny]" value="1"%4$s /> %5$s</label>'
            . '</p>',
            esc_attr($name),
            checked((bool) $settings['media_buttons'], true, false),
            esc_html__('Medien-Buttons anzeigen', 'bs-fabric'),
            checked((bool) $settings['teeny'], true, false),
            esc_html__('Kompakter Editor', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        if (! is_scalar($value) || $value === '') {
            return '';
        }

        return wp_kses_post((string) $value);
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
