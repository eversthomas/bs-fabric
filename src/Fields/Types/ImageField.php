<?php

declare(strict_types=1);

namespace BsFabric\Fields\Types;

final class ImageField implements FieldTypeInterface
{
    public function getType(): string
    {
        return 'image';
    }

    public function getLabel(): string
    {
        return __('Image', 'bs-fabric');
    }

    public function getDefaultSettings(): array
    {
        return [
            'return_format' => 'id',
        ];
    }

    public function sanitizeValue(mixed $value): mixed
    {
        if ($value === null || $value === '') {
            return 0;
        }

        if (is_array($value)) {
            $value = $value['id'] ?? $value[0] ?? 0;
        }

        return absint($value);
    }

    public function renderInput(array $field, mixed $value): string
    {
        $name = $this->fieldName($field);
        $attachmentId = $this->sanitizeValue($value);
        $preview = $attachmentId > 0
            ? wp_get_attachment_image($attachmentId, 'thumbnail', false, ['class' => 'bs-fabric-field-image-preview'])
            : '';

        return sprintf(
            '<div class="bs-fabric-field bs-fabric-field-image" data-bs-fabric-image-field>'
            . '%s'
            . '<input type="hidden" name="%s" value="%d" />'
            . '<button type="button" class="button bs-fabric-field-image-select">%s</button>'
            . '<button type="button" class="button bs-fabric-field-image-remove">%s</button>'
            . '</div>',
            $preview,
            esc_attr($name),
            $attachmentId,
            esc_html__('Bild wählen', 'bs-fabric'),
            esc_html__('Entfernen', 'bs-fabric')
        );
    }

    public function renderSettings(array $field): string
    {
        $settings = $this->mergeSettings($field);
        $name = $this->settingsName($field);

        return sprintf(
            '<p class="bs-fabric-field-setting">'
            . '<label for="%1$s-return_format">%2$s</label>'
            . '<select id="%1$s-return_format" name="%1$s[return_format]">'
            . '<option value="id"%3$s>%4$s</option>'
            . '<option value="array"%5$s>%6$s</option>'
            . '</select>'
            . '</p>',
            esc_attr($name),
            esc_html__('Rückgabeformat', 'bs-fabric'),
            selected((string) $settings['return_format'], 'id', false),
            esc_html__('Attachment-ID', 'bs-fabric'),
            selected((string) $settings['return_format'], 'array', false),
            esc_html__('Array (Metadaten)', 'bs-fabric')
        );
    }

    public function renderValue(mixed $value): mixed
    {
        $attachmentId = $this->sanitizeValue($value);

        if ($attachmentId <= 0) {
            return null;
        }

        $url = wp_get_attachment_url($attachmentId);

        if (! is_string($url) || $url === '') {
            return null;
        }

        return [
            'id' => $attachmentId,
            'url' => esc_url($url),
            'alt' => esc_attr(get_post_meta($attachmentId, '_wp_attachment_image_alt', true)),
        ];
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
